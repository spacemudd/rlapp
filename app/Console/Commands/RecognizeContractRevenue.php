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
            $now = Carbon::now($timezone);

            // Query active contracts
            $query = Contract::where('status', 'active')
                ->with(['vehicle.branch', 'team.entity']);

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
                    $result = $this->processContract($contract, $now, $timezone);
                    
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
    private function processContract(Contract $contract, Carbon $currentTime, string $timezone): array
    {
        // Parse contract start in Dubai timezone (keep original time component)
        $startDateTime = Carbon::parse($contract->start_date)->timezone($timezone);

        if ($currentTime->lt($startDateTime)) {
            return [
                'processed' => false,
                'reason' => 'Contract has not started yet',
                'days' => 0,
                'amount' => 0,
            ];
        }

        $secondsSinceStart = $startDateTime->diffInSeconds($currentTime, false);
        if ($secondsSinceStart < 0) {
            return [
                'processed' => false,
                'reason' => 'Contract has not started yet',
                'days' => 0,
                'amount' => 0,
            ];
        }

        $firstDayGraceSeconds = 3 * 3600; // 3 hour grace period
        if ($secondsSinceStart < $firstDayGraceSeconds) {
            return [
                'processed' => false,
                'reason' => 'Initial grace period not yet elapsed',
                'days' => 0,
                'amount' => 0,
            ];
        }

        // First day is billable once grace period ends
        $eligibleDays = 1;

        // Subsequent days accrue once 24h + 1h buffer has passed
        $secondsAfterInitialBuffer = max(0, $secondsSinceStart - 3600); // 1 hour buffer past each 24h cycle
        if ($secondsAfterInitialBuffer >= 86400) {
            $eligibleDays += intdiv($secondsAfterInitialBuffer, 86400);
        }

        $totalContractDays = max(1, (int) $contract->total_days);
        if ($contract->status !== 'active') {
            $eligibleDays = min($eligibleDays, $totalContractDays);
        }

        // Check how many days have already been recognized
        $recognizedDays = $this->getRecognizedDays($contract);

        // Calculate days that need recognition
        $daysToRecognize = $eligibleDays - $recognizedDays;

        if ($daysToRecognize <= 0) {
            return [
                'processed' => false,
                'reason' => 'Revenue already recognized for all elapsed days',
                'days' => 0,
                'amount' => 0,
            ];
        }

        // Calculate daily rate and split into net rental and VAT
        $dailyRate = round($contract->total_amount / $totalContractDays, 2);
        
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
        $vatDaysToRecognize = max(0, $eligibleDays - $recognizedVATDays);

        // Process each unrecognized day
        $totalRevenueAmount = 0;
        $totalVATAmount = 0;
        $entity = optional($contract->team)->entity;

        if (!$entity) {
            Log::error('Missing IFRS entity for contract revenue recognition', [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'team_id' => $contract->team_id,
            ]);

            return [
                'processed' => false,
                'reason' => 'IFRS entity not configured for contract team',
                'days' => 0,
                'amount' => 0,
            ];
        }

        $this->accountingService->setEntityContext($entity);

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $daysToRecognize; $i++) {
                $dayNumber = $recognizedDays + $i + 1;
                $recognitionMoment = $dayNumber === 1
                    ? $startDateTime->copy()->addHours(3)
                    : $startDateTime->copy()->addHours(($dayNumber - 1) * 24 + 1);

                // Record revenue recognition
                $revenueTransaction = $this->accountingService->recordDailyRevenueRecognition(
                    $contract,
                    $recognitionMoment,
                    $dayNumber,
                    $dailyNetRental
                );

                $totalRevenueAmount += $dailyNetRental;

                Log::info('Revenue recognized for contract', [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'day_number' => $dayNumber,
                    'recognition_date' => $recognitionMoment->toDateString(),
                    'amount' => $dailyNetRental,
                    'transaction_id' => $revenueTransaction->id,
                ]);
            }

            // Process VAT recognition for unrecognized days
            for ($i = 0; $i < $vatDaysToRecognize; $i++) {
                $dayNumber = $recognizedVATDays + $i + 1;
                $recognitionMoment = $dayNumber === 1
                    ? $startDateTime->copy()->addHours(3)
                    : $startDateTime->copy()->addHours(($dayNumber - 1) * 24 + 1);

                // Record VAT recognition
                $vatTransaction = $this->accountingService->recordDailyVATRecognition(
                    $contract,
                    $recognitionMoment,
                    $dayNumber,
                    $dailyVAT
                );

                $totalVATAmount += $dailyVAT;

                Log::info('VAT recognized for contract', [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'day_number' => $dayNumber,
                    'recognition_date' => $recognitionMoment->toDateString(),
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
        } finally {
            $this->accountingService->clearEntityContext();
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

