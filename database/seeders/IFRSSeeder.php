<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use IFRS\Models\Entity;
use IFRS\Models\Currency;
use IFRS\Models\Account;
use IFRS\Models\ReportingPeriod;
use Carbon\Carbon;

class IFRSSeeder extends Seeder
{
    /**
     * Run the IFRS system initialization seeder.
     */
    public function run(): void
    {
        $this->command->info('🏗️  Initializing IFRS Accounting System...');
        
        try {
            // Step 1: Create or get IFRS Entity
            $entity = $this->createEntity();
            $this->command->info("✅ Entity: {$entity->name} (ID: {$entity->id})");
            
            // Step 2: Create or get default currency
            $currency = $this->createDefaultCurrency($entity);
            $this->command->info("✅ Currency: {$currency->name} (ID: {$currency->id})");
            
            // Step 3: Update entity with currency if needed
            if (!$entity->currency_id) {
                $entity->update(['currency_id' => $currency->id]);
                $this->command->info("✅ Updated entity with default currency");
            }
            
            // Step 4: Create complete chart of accounts
            $accountsCreated = $this->createChartOfAccounts($entity, $currency);
            $this->command->info("✅ Created {$accountsCreated} accounts in chart of accounts");
            
            // Step 5: Create reporting periods
            $periodsCreated = $this->createReportingPeriods($entity);
            $this->command->info("✅ Created {$periodsCreated} reporting periods");
            
            // Step 6: Ensure all teams have entity_id assigned
            $this->assignEntityToTeams($entity);
            
            $this->command->newLine();
            $this->command->info('🎉 IFRS Accounting System initialized successfully!');
            
            $this->logInitializationSummary($entity, $currency);
            
        } catch (\Exception $e) {
            $this->command->error('❌ Failed to initialize IFRS system:');
            $this->command->error($e->getMessage());
            
            Log::error('IFRS Seeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create or get the IFRS Entity.
     */
    private function createEntity(): Entity
    {
        $entityName = config('app.name', 'Laravel');
        
        $entity = Entity::where('name', $entityName)->first();
        
        if (!$entity) {
            // Create entity without currency first (to avoid circular dependency)
            $entity = Entity::create([
                'name' => $entityName,
                'currency_id' => null, // Will be set after currency creation
            ]);
            
            $this->command->line("   Created new entity: {$entityName}");
        } else {
            $this->command->line("   Found existing entity: {$entityName}");
        }
        
        return $entity;
    }
    
    /**
     * Create or get the default currency (AED).
     */
    private function createDefaultCurrency(Entity $entity): Currency
    {
        $currency = Currency::where('currency_code', 'AED')->first();
        
        if (!$currency) {
            $currency = Currency::create([
                'name' => 'UAE Dirham',
                'currency_code' => 'AED',
                'entity_id' => $entity->id,
            ]);
            
            $this->command->line("   Created new currency: UAE Dirham (AED)");
        } else {
            // Ensure currency belongs to correct entity
            if ($currency->entity_id !== $entity->id) {
                $currency->update(['entity_id' => $entity->id]);
                $this->command->line("   Updated currency entity association");
            }
            $this->command->line("   Found existing currency: UAE Dirham (AED)");
        }
        
        return $currency;
    }
    
    /**
     * Create a complete chart of accounts structure.
     */
    private function createChartOfAccounts(Entity $entity, Currency $currency): int
    {
        $accounts = [
            // Assets (1000-1999)
            ['name' => 'Cash', 'type' => Account::BANK, 'code' => '1100'],
            ['name' => 'Petty Cash', 'type' => Account::CURRENT_ASSET, 'code' => '1105'],
            ['name' => 'Accounts Receivable', 'type' => Account::RECEIVABLE, 'code' => '1200'],
            ['name' => 'Prepaid Expenses', 'type' => Account::CURRENT_ASSET, 'code' => '1300'],
            ['name' => 'Vehicle Assets', 'type' => Account::NON_CURRENT_ASSET, 'code' => '1500'],
            ['name' => 'Accumulated Depreciation - Vehicles', 'type' => Account::CONTRA_ASSET, 'code' => '1501'],
            ['name' => 'Equipment', 'type' => Account::NON_CURRENT_ASSET, 'code' => '1600'],
            ['name' => 'Accumulated Depreciation - Equipment', 'type' => Account::CONTRA_ASSET, 'code' => '1601'],
            
            // Liabilities (2000-2999)
            ['name' => 'Accounts Payable', 'type' => Account::PAYABLE, 'code' => '2100'],
            ['name' => 'Accrued Expenses', 'type' => Account::CURRENT_LIABILITY, 'code' => '2150'],
            ['name' => 'VAT Payable', 'type' => Account::CURRENT_LIABILITY, 'code' => '2200'],
            ['name' => 'Customer Deposits', 'type' => Account::CURRENT_LIABILITY, 'code' => '2300'],
            ['name' => 'Loans Payable', 'type' => Account::NON_CURRENT_LIABILITY, 'code' => '2500'],
            
            // Equity (3000-3999)
            ['name' => 'Owner\'s Capital', 'type' => Account::EQUITY, 'code' => '3100'],
            ['name' => 'Retained Earnings', 'type' => Account::EQUITY, 'code' => '3200'],
            ['name' => 'Current Year Earnings', 'type' => Account::EQUITY, 'code' => '3300'],
            
            // Revenue (4000-4999)
            ['name' => 'Rental Revenue', 'type' => Account::OPERATING_REVENUE, 'code' => '4001'],
            ['name' => 'Late Fees Revenue', 'type' => Account::OPERATING_REVENUE, 'code' => '4010'],
            ['name' => 'Insurance Revenue', 'type' => Account::OPERATING_REVENUE, 'code' => '4020'],
            ['name' => 'Other Income', 'type' => Account::NON_OPERATING_REVENUE, 'code' => '4900'],
            
            // Expenses (5000-5999)
            ['name' => 'Vehicle Maintenance Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5100'],
            ['name' => 'Fuel Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5110'],
            ['name' => 'Insurance Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5200'],
            ['name' => 'Depreciation Expense - Vehicles', 'type' => Account::OPERATING_EXPENSE, 'code' => '5300'],
            ['name' => 'Depreciation Expense - Equipment', 'type' => Account::OPERATING_EXPENSE, 'code' => '5310'],
            ['name' => 'Office Rent Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5400'],
            ['name' => 'Utilities Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5410'],
            ['name' => 'Marketing Expense', 'type' => Account::OPERATING_EXPENSE, 'code' => '5500'],
            ['name' => 'Professional Fees', 'type' => Account::OPERATING_EXPENSE, 'code' => '5600'],
            ['name' => 'Bank Fees', 'type' => Account::OTHER_EXPENSE, 'code' => '5900'],
        ];
        
        $createdCount = 0;
        
        foreach ($accounts as $accountData) {
            $existingAccount = Account::where('entity_id', $entity->id)
                ->where('code', $accountData['code'])
                ->first();
                
            if (!$existingAccount) {
                Account::create([
                    'name' => $accountData['name'],
                    'account_type' => $accountData['type'],
                    'code' => $accountData['code'],
                    'currency_id' => $currency->id,
                    'entity_id' => $entity->id,
                ]);
                
                $createdCount++;
                $this->command->line("   Created: {$accountData['name']} ({$accountData['code']})");
            }
        }
        
        if ($createdCount === 0) {
            $this->command->line("   All accounts already exist");
        }
        
        return $createdCount;
    }
    
    /**
     * Create reporting periods for current and next year.
     */
    private function createReportingPeriods(Entity $entity): int
    {
        $currentYear = Carbon::now()->year;
        $years = [$currentYear, $currentYear + 1];
        $createdCount = 0;
        
        foreach ($years as $year) {
            $existingPeriod = ReportingPeriod::where('entity_id', $entity->id)
                ->where('calendar_year', $year)
                ->first();
                
            if (!$existingPeriod) {
                ReportingPeriod::create([
                    'entity_id' => $entity->id,
                    'calendar_year' => $year,
                    'period_count' => 12, // Monthly periods
                ]);
                
                $createdCount++;
                $this->command->line("   Created reporting period for {$year}");
            }
        }
        
        if ($createdCount === 0) {
            $this->command->line("   All reporting periods already exist");
        }
        
        return $createdCount;
    }
    
    /**
     * Log initialization summary.
     */
    private function logInitializationSummary(Entity $entity, Currency $currency): void
    {
        $accountsCount = Account::where('entity_id', $entity->id)->count();
        $periodsCount = ReportingPeriod::where('entity_id', $entity->id)->count();
        
        Log::info('IFRS system initialized successfully', [
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'currency_id' => $currency->id,
            'currency_code' => $currency->currency_code,
            'accounts_count' => $accountsCount,
            'reporting_periods_count' => $periodsCount,
        ]);
        
        $this->command->info("📊 Summary:");
        $this->command->line("   - Entity: {$entity->name}");
        $this->command->line("   - Currency: {$currency->name} ({$currency->currency_code})");
        $this->command->line("   - Total Accounts: {$accountsCount}");
        $this->command->line("   - Reporting Periods: {$periodsCount}");
    }
    
    /**
     * Assign the default entity to teams that don't have entity_id set.
     */
    private function assignEntityToTeams(Entity $entity): void
    {
        $teamsWithoutEntity = \App\Models\Team::whereNull('entity_id')->get();
        
        if ($teamsWithoutEntity->count() > 0) {
            foreach ($teamsWithoutEntity as $team) {
                $team->update(['entity_id' => $entity->id]);
            }
            
            $this->command->info("✅ Assigned entity to {$teamsWithoutEntity->count()} teams");
        } else {
            $this->command->line("   All teams already have entity assignments");
        }
    }
}
