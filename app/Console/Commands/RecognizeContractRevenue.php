<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IFRS\Models\Transaction;

class RecognizeContractRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:recognize-revenue {--contract_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recognize daily rental revenue for active contracts based on IFRS accrual accounting';

    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        parent::__construct();
        $this->accountingService = $accountingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎯 Starting daily revenue recognition for active contracts...');
        $this->newLine();

        try {
            // Get timezone for Dubai
            $timezone = 'Asia/Dubai';
            $today = Carbon::now($timezone)->startOfDay();

            // Query active contracts
            $query = Contract::where('status', 'active')
                ->with(['vehicle.branch']);

            // Optional: Process specific contract for testing
            if ($this->option('contract_id')) {
                $query->where('id', $this->option('contract_id'));
            }

            $contracts = $query->get();

            if ($contracts->isEmpty()) {
                $this->warn('No active contracts found.');
                return Command::SUCCESS;
            }

            $this->info("Found {$contracts->count()} active contract(s) to process.");
            $this->newLine();

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $totalRevenueRecognized = 0;
            $totalVATRecognized = 0;

            foreach ($contracts as $contract) {
                try {
                    $result = $this->processContract($contract, $today, $timezone);
                    
                    if ($result['processed']) {
                        $processedCount++;
                        $totalRevenueRecognized += $result['amount'];
                        $totalVATRecognized += $result['vat_amount'] ?? 0;
                        
                        $revenueMsg = "Revenue: {$result['days']} day(s) = {$contract->currency} " . number_format($result['amount'], 2);
                        $vatMsg = isset($result['vat_amount']) && $result['vat_amount'] > 0 
                            ? ", VAT: {$result['vat_days']} day(s) = {$contract->currency} " . number_format($result['vat_amount'], 2)
                            : '';
                        
                        $this->line("✅ Contract {$contract->contract_number}: {$revenueMsg}{$vatMsg}");
                    } else {
                        $skippedCount++;
                        $this->line("⏭️  Contract {$contract->contract_number}: {$result['reason']}");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("❌ Contract {$contract->contract_number}: {$e->getMessage()}");
                    
                    Log::error('Revenue recognition failed for contract', [
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->newLine();
            $this->info('📊 Summary:');
            $this->line("   - Processed: {$processedCount} contract(s)");
            $this->line("   - Skipped: {$skippedCount} contract(s)");
            $this->line("   - Errors: {$errorCount} contract(s)");
            $this->line("   - Total Revenue Recognized: AED " . number_format($totalRevenueRecognized, 2));
            $this->line("   - Total VAT Recognized: AED " . number_format($totalVATRecognized, 2));
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn('⚠️  Some contracts had errors. Check logs for details.');
                return Command::FAILURE;
            }

            $this->info('✨ Revenue recognition completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Revenue recognition command failed: ' . $e->getMessage());
            Log::error('Revenue recognition command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Process a single contract for revenue recognition.
     */
    private function processContract(Contract $contract, Carbon $today, string $timezone): array
    {
        // Parse contract dates in Dubai timezone
        $startDate = Carbon::parse($contract->start_date)->timezone($timezone)->startOfDay();
        $endDate = Carbon::parse($contract->end_date)->timezone($timezone)->startOfDay();

        // Calculate days elapsed since contract start
        if ($today->lt($startDate)) {
            return [
                'processed' => false,
                'reason' => 'Contract has not started yet',
                'days' => 0,
                'amount' => 0,
            ];
        }

        // Clamp today to contract period (don't recognize beyond end date)
        $clampedToday = $today->gt($endDate) ? $endDate : $today;

        // Calculate total days elapsed (inclusive of start day)
        $daysElapsed = $startDate->diffInDays($clampedToday) + 1;

        // Check how many days have already been recognized
        $recognizedDays = $this->getRecognizedDays($contract);

        // Calculate days that need recognition
        $daysToRecognize = $daysElapsed - $recognizedDays;

        if ($daysToRecognize <= 0) {
            return [
                'processed' => false,
                'reason' => 'Revenue already recognized for all elapsed days',
                'days' => 0,
                'amount' => 0,
            ];
        }

        // Calculate daily rate and split into net rental and VAT
        $totalDays = max(1, (int) $contract->total_days);
        $dailyRate = round($contract->total_amount / $totalDays, 2);
        
        // Split into net rental and VAT based on contract setting
        $isVatInclusive = $contract->is_vat_inclusive ?? true;
        $vatRate = 0.05; // 5% VAT
        
        if ($isVatInclusive) {
            // Price includes VAT: split backwards
            $dailyNetRental = round($dailyRate / 1.05, 2);
            $dailyVAT = round($dailyRate - $dailyNetRental, 2);
        } else {
            // Price excludes VAT: calculate forwards
            $dailyNetRental = $dailyRate;
            $dailyVAT = round($dailyRate * $vatRate, 2);
        }

        // Check how many days have already been recognized for VAT
        $recognizedVATDays = $this->getRecognizedVATDays($contract);
        $vatDaysToRecognize = $daysElapsed - $recognizedVATDays;

        // Process each unrecognized day
        $totalRevenueAmount = 0;
        $totalVATAmount = 0;
        DB::beginTransaction();

        try {
            for ($i = 0; $i < $daysToRecognize; $i++) {
                $dayNumber = $recognizedDays + $i + 1;
                $recognitionDate = $startDate->copy()->addDays($dayNumber - 1);

                // Record revenue recognition
                $revenueTransaction = $this->accountingService->recordDailyRevenueRecognition(
                    $contract,
                    $recognitionDate,
                    $dayNumber,
                    $dailyNetRental
                );

                $totalRevenueAmount += $dailyNetRental;

                Log::info('Revenue recognized for contract', [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'day_number' => $dayNumber,
                    'recognition_date' => $recognitionDate->toDateString(),
                    'amount' => $dailyNetRental,
                    'transaction_id' => $revenueTransaction->id,
                ]);
            }

            // Process VAT recognition for unrecognized days
            for ($i = 0; $i < $vatDaysToRecognize; $i++) {
                $dayNumber = $recognizedVATDays + $i + 1;
                $recognitionDate = $startDate->copy()->addDays($dayNumber - 1);

                // Record VAT recognition
                $vatTransaction = $this->accountingService->recordDailyVATRecognition(
                    $contract,
                    $recognitionDate,
                    $dayNumber,
                    $dailyVAT
                );

                $totalVATAmount += $dailyVAT;

                Log::info('VAT recognized for contract', [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'day_number' => $dayNumber,
                    'recognition_date' => $recognitionDate->toDateString(),
                    'amount' => $dailyVAT,
                    'transaction_id' => $vatTransaction->id,
                ]);
            }

            DB::commit();

            return [
                'processed' => true,
                'days' => $daysToRecognize,
                'amount' => $totalRevenueAmount,
                'vat_days' => $vatDaysToRecognize,
                'vat_amount' => $totalVATAmount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get the number of days already recognized for a contract.
     */
    private function getRecognizedDays(Contract $contract): int
    {
        return Transaction::where('narration', 'LIKE', "Revenue recognition for Contract {$contract->contract_number} - Day %")
            ->count();
    }

    /**
     * Get the number of VAT days already recognized for a contract.
     */
    private function getRecognizedVATDays(Contract $contract): int
    {
        return Transaction::where('narration', 'LIKE', "VAT recognition for Contract {$contract->contract_number} - Day %")
            ->count();
    }
}

