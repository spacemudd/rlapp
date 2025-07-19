<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AccountingService;
use IFRS\Models\Entity;
use IFRS\Models\Currency;
use IFRS\Models\ReportingPeriod;
use Exception;

class SetupAccounting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:setup {--force : Force setup even if already configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the IFRS accounting system with initial chart of accounts, entity, and currency';

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
        $this->info('🚀 Setting up IFRS Accounting System...');

        try {
            // Check if already set up
            if (!$this->option('force') && $this->isAlreadySetup()) {
                $this->warn('Accounting system appears to be already set up.');
                if (!$this->confirm('Do you want to continue anyway?')) {
                    return Command::SUCCESS;
                }
            }

            // Step 1: Set up Entity
            $this->info('📊 Setting up Entity...');
            $entity = $this->setupEntity();
            $this->info("✅ Entity '{$entity->name}' created/verified.");

            // Step 2: Set up Currencies
            $this->info('💱 Setting up Currencies...');
            $currencies = $this->setupCurrencies($entity);
            $this->info("✅ Currencies set up: " . implode(', ', array_keys($currencies)));

            // Step 3: Set up Reporting Period
            $this->info('📅 Setting up Reporting Period...');
            $reportingPeriod = $this->setupReportingPeriod($entity);
            $this->info("✅ Reporting period for {$reportingPeriod->calendar_year} created/verified.");

            // Step 4: Set up Chart of Accounts
            $this->info('📋 Setting up Chart of Accounts...');
            $this->accountingService->setupChartOfAccounts();
            $this->info('✅ Chart of Accounts set up successfully.');

            // Step 5: Display summary
            $this->displaySummary($entity);

            $this->info('🎉 IFRS Accounting System setup completed successfully!');
            
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Check if the accounting system is already set up.
     */
    private function isAlreadySetup(): bool
    {
        $entity = Entity::where('name', config('app.name', 'Laravel'))->first();
        if (!$entity) {
            return false;
        }

        // Check if we have basic accounts
        $accountCount = \IFRS\Models\Account::where('entity_id', $entity->id)->count();
        return $accountCount > 5; // If we have more than 5 accounts, assume it's set up
    }

    /**
     * Set up the main entity.
     */
    private function setupEntity(): Entity
    {
        $entityName = config('app.name', 'Laravel Rental Company');
        
        $entity = Entity::where('name', $entityName)->first();
        
        if (!$entity) {
            // Create entity without currency_id first, then update it
            $entity = Entity::create([
                'name' => $entityName,
                'multi_currency' => true,
            ]);

            // Now create the default currency with the entity
            $defaultCurrency = Currency::create([
                'name' => 'UAE Dirham',
                'currency_code' => 'AED',
                'entity_id' => $entity->id,
            ]);

            // Update entity with the default currency
            $entity->update(['currency_id' => $defaultCurrency->id]);
        }

        return $entity;
    }

    /**
     * Set up currencies.
     */
    private function setupCurrencies(Entity $entity): array
    {
        $currencies = [
            'AED' => 'UAE Dirham',
            'USD' => 'US Dollar', 
            'EUR' => 'Euro',
            'SAR' => 'Saudi Riyal',
        ];

        $createdCurrencies = [];

        foreach ($currencies as $code => $name) {
            $currency = Currency::firstOrCreate([
                'currency_code' => $code,
            ], [
                'name' => $name,
                'entity_id' => $entity->id,
            ]);

            $createdCurrencies[$code] = $currency;
        }

        return $createdCurrencies;
    }

    /**
     * Set up reporting period for the current year.
     */
    private function setupReportingPeriod(Entity $entity): ReportingPeriod
    {
        $currentYear = now()->year;
        
        $reportingPeriod = ReportingPeriod::where('entity_id', $entity->id)
            ->where('calendar_year', $currentYear)
            ->first();

        if (!$reportingPeriod) {
            $reportingPeriod = ReportingPeriod::create([
                'entity_id' => $entity->id,
                'calendar_year' => $currentYear,
                'period_count' => 12, // Monthly periods
            ]);
        }

        return $reportingPeriod;
    }

    /**
     * Display a summary of what was set up.
     */
    private function displaySummary(Entity $entity): void
    {
        $this->newLine();
        $this->info('📊 SETUP SUMMARY');
        $this->info('================');
        
        // Entity info
        $this->line("🏢 Entity: {$entity->name}");
        $this->line("🆔 Entity ID: {$entity->id}");
        
        // Currency info
        $currencies = Currency::where('entity_id', $entity->id)->pluck('currency_code')->toArray();
        $this->line("💱 Currencies: " . implode(', ', $currencies));
        
        // Account count
        $accountCount = \IFRS\Models\Account::where('entity_id', $entity->id)->count();
        $this->line("📋 Accounts created: {$accountCount}");
        
        // Reporting period
        $reportingPeriod = ReportingPeriod::where('entity_id', $entity->id)->first();
        if ($reportingPeriod) {
            $this->line("📅 Reporting Period: {$reportingPeriod->calendar_year}");
        }
        
        $this->newLine();
        $this->info('🔧 NEXT STEPS:');
        $this->info('- Create bank accounts: Banks → Add New');
        $this->info('- Set up cash accounts for petty cash and registers');
        $this->info('- Ensure customers have payment terms configured');
        $this->info('- Add vehicle acquisition costs and dates for depreciation');
        $this->info('- Start creating invoices and payments - they will automatically sync!');
        
        $this->newLine();
    }
}
