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
        $entityName = 'Luxuria Cars Rental L.L.C.';
        
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
     * Convert Arabic hierarchical code to integer for IFRS compatibility.
     * Uses deterministic mapping based on code structure to fit within MySQL INT limits.
     */
    private function convertArabicCodeToInteger(string $arabicCode): int
    {
        // Remove leading zeros
        $code = ltrim($arabicCode, '0');
        
        // If empty after removing zeros, return 0
        if (empty($code)) {
            return 0;
        }
        
        // Determine category base from first digit(s)
        $firstDigit = (int) substr($arabicCode, 0, 1);
        
        // Category base ranges (using ranges that fit in INT)
        $baseRanges = [
            0 => 100000,     // Special case for codes starting with 0
            1 => 100000,     // Assets: 100,000 - 199,999
            2 => 200000,     // Liabilities: 200,000 - 299,999
            3 => 300000,     // Equity: 300,000 - 399,999
            4 => 400000,     // Revenue: 400,000 - 499,999
            5 => 500000,     // Expenses: 500,000 - 599,999
        ];
        
        $base = $baseRanges[$firstDigit] ?? 100000;
        
        // For deterministic mapping, use CRC32 of the code
        // This ensures same code always maps to same number
        $hash = crc32($arabicCode);
        
        // Convert to positive unsigned integer
        if ($hash < 0) {
            $hash = $hash + 4294967296;
        }
        
        // Map to range within category (0-99999 per category)
        $offset = $hash % 99999;
        
        $result = $base + $offset;
        
        // Ensure it fits in signed INT (2,147,483,647)
        return min($result, 2147483647);
    }
    
    /**
     * Determine IFRS account type based on Arabic category and code pattern.
     */
    private function determineAccountType(string $arabicCode, string $categoryName): string
    {
        // Main category mapping
        if (str_starts_with($arabicCode, '01')) {
            // Assets category
            if (str_starts_with($arabicCode, '0101')) {
                // Fixed Assets (0101-010108)
                if (str_contains($categoryName, 'اهلاك') || str_contains($categoryName, 'مخصص')) {
                    return Account::CONTRA_ASSET;
                }
                return Account::NON_CURRENT_ASSET;
            } elseif (str_starts_with($arabicCode, '0102')) {
                // Current Assets (0102)
                if (str_starts_with($arabicCode, '010201')) {
                    // Cash & Equivalents (010201)
                    if (str_starts_with($arabicCode, '01020103')) {
                        return Account::BANK;
                    }
                    return Account::CURRENT_ASSET;
                } elseif (str_starts_with($arabicCode, '010202')) {
                    // Receivables (010202)
                    return Account::RECEIVABLE;
                } elseif (str_starts_with($arabicCode, '010203')) {
                    // Inventory (010203)
                    return Account::INVENTORY;
                }
                return Account::CURRENT_ASSET;
            }
            return Account::CURRENT_ASSET;
        } elseif (str_starts_with($arabicCode, '02')) {
            // Liabilities category
            if (str_starts_with($arabicCode, '0201')) {
                // Current Liabilities (0201)
                if (str_starts_with($arabicCode, '020101')) {
                    // Payables (020101)
                    return Account::PAYABLE;
                }
                return Account::CURRENT_LIABILITY;
            } elseif (str_starts_with($arabicCode, '0202')) {
                // Long-term Liabilities (0202)
                return Account::NON_CURRENT_LIABILITY;
            }
            return Account::CURRENT_LIABILITY;
        } elseif (str_starts_with($arabicCode, '03')) {
            // Equity category
            return Account::EQUITY;
        } elseif (str_starts_with($arabicCode, '04')) {
            // Revenue category
            if (str_starts_with($arabicCode, '0401')) {
                // Operating Revenue (0401)
                return Account::OPERATING_REVENUE;
            } elseif (str_starts_with($arabicCode, '0402') || str_starts_with($arabicCode, '0403')) {
                // Other Revenue (0402, 0403)
                return Account::NON_OPERATING_REVENUE;
            }
            return Account::OPERATING_REVENUE;
        } elseif (str_starts_with($arabicCode, '05')) {
            // Expenses category
            if (str_starts_with($arabicCode, '0501')) {
                // Cost of Goods Sold (0501)
                return Account::DIRECT_EXPENSE;
            } elseif (str_starts_with($arabicCode, '0502')) {
                // General & Administrative Expenses (0502)
                return Account::OPERATING_EXPENSE;
            }
            return Account::OPERATING_EXPENSE;
        }
        
        // Default fallback
        return Account::CURRENT_ASSET;
    }
    
    /**
     * Create a complete chart of accounts structure based on Arabic chart.
     */
    private function createChartOfAccounts(Entity $entity, Currency $currency): int
    {
        $accounts = [
            // ============ الأصول (01 - Assets) ============
            
            // 01: الأصول (Main Assets Category)
            ['arabic_code' => '01', 'name' => 'الأصول', 'type' => Account::CURRENT_ASSET],
            
            // 0101: الأصول الثابتة (Fixed Assets)
            ['arabic_code' => '0101', 'name' => 'حـ / الاصول الثابتة', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010101', 'name' => 'حـ / الأراضي', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010102', 'name' => 'حـ / المباني', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010103', 'name' => 'حـ / السيارات', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010301', 'name' => 'سيارات الخدمة', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010302', 'name' => 'سيارات التاجير', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010104', 'name' => 'حـ / الاجهزة', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010401', 'name' => 'حاسب آلي', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010402', 'name' => 'طابعة مستندات', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010403', 'name' => 'اجهزة جي بي اس', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010105', 'name' => 'حـ / الشهرة', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010106', 'name' => 'حـ / البرامج', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010601', 'name' => 'برنامج نود للتأجير', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010602', 'name' => 'برنامج سماك المحاسبي', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010107', 'name' => 'الات ومعدات', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '01010701', 'name' => 'ماكينة كمبروسر', 'type' => Account::NON_CURRENT_ASSET],
            ['arabic_code' => '010108', 'name' => 'حــ / الاثاث', 'type' => Account::NON_CURRENT_ASSET],
            
            // 0102: الأصول المتداولة (Current Assets)
            ['arabic_code' => '0102', 'name' => 'حـ / الاصول المتداولة', 'type' => Account::CURRENT_ASSET],
            
            // 010201: النقدية وما في حكمها (Cash & Equivalents)
            ['arabic_code' => '010201', 'name' => 'حـ / النقدية وما في حكمها', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '01020101', 'name' => 'حـ / النقدية بالصندوق', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010101', 'name' => 'حـ / النقدية بالصندوق الرئيسي', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010102', 'name' => 'حـ / النقدية بالصندوق الفرعي عجمان', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '01020102', 'name' => 'حـ / العهد النقدية', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010201', 'name' => 'عهدة مالية عماد فؤاد', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010202', 'name' => 'عهدة مالية محمد سعيد', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010203', 'name' => 'عهدة صيانة السيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010204', 'name' => 'عهدة مالية عبد الغني', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '01020103', 'name' => 'البنوك', 'type' => Account::BANK],
            ['arabic_code' => '0102010301', 'name' => 'حسابات بنك WIO', 'type' => Account::BANK],
            ['arabic_code' => '0102010301001', 'name' => 'حساب بنك WIO', 'type' => Account::BANK],
            ['arabic_code' => '0102010301002', 'name' => 'بطاقة أتمانية بنك WIO', 'type' => Account::BANK],
            ['arabic_code' => '0102010301003', 'name' => 'شيكات بنك WIO', 'type' => Account::BANK],
            ['arabic_code' => '0102010302', 'name' => 'حسابات بنك المشرق', 'type' => Account::BANK],
            ['arabic_code' => '0102010302001', 'name' => 'حساب بنك المشرق', 'type' => Account::BANK],
            ['arabic_code' => '0102010302002', 'name' => 'بطاقة أتمانية بنك المشرق', 'type' => Account::BANK],
            ['arabic_code' => '01020104', 'name' => 'شركات تحصيل الاموال', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010401', 'name' => 'حـ / ماكينة ماجناتي', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010402', 'name' => 'شركة تابي', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010403', 'name' => 'شركة تمارا', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010404', 'name' => 'تحصيلات كاش', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102010405', 'name' => 'تحصيلات عبر البطاقة', 'type' => Account::CURRENT_ASSET],
            
            // 010202: الذمم المدينة (Receivables)
            ['arabic_code' => '010202', 'name' => 'حـ / الذمم المدينة', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '01020201', 'name' => 'حـ / ذمم العملاء', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '0102020101', 'name' => 'حـ / عملاء افراد', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '0102020102', 'name' => 'حـ / عملاء شركات', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '01020202', 'name' => 'حـ / ذمم الموظفين', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '01020203', 'name' => 'حـ / أرصدة مدينة اخرى', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301', 'name' => 'المصروفات المدفوعة مقدما', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301001', 'name' => 'مقدم تأمين طبي', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301002', 'name' => 'مقدم ايجار', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301002001', 'name' => 'مقدم ايجار مكتب الادارة', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301002002', 'name' => 'مقدم ايجار فروع ادارية', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301002003', 'name' => 'مقدم ايجار سكن موظفين', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301003', 'name' => 'مقدم مصروف سيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301003001', 'name' => 'مقدم مصاريف بوابات السيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301003002', 'name' => 'مقدم تامين سيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301003003', 'name' => 'مصروفات مدفوعة مقدما للسيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301004', 'name' => 'مقدم تاشيرات واقامات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301005', 'name' => 'مقدم تامينات مستردة', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020301006', 'name' => 'مصروفات مدفوعة مقدما دعاية واعلان', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020302', 'name' => 'حـ / الأيرادات المستحقة', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020303', 'name' => 'حـ / المخزون', 'type' => Account::INVENTORY],
            ['arabic_code' => '0102020303001', 'name' => 'ح/ المخزون الرئيسي', 'type' => Account::INVENTORY],
            ['arabic_code' => '0102020303002', 'name' => 'مخزون الخدمات', 'type' => Account::INVENTORY],
            ['arabic_code' => '0102020304', 'name' => 'الضرائب المدفوعة', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020304001', 'name' => 'ح/ ضرائب جمركية', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020304002', 'name' => 'ضريبة مدفوعة القيمة المضافة 5%', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020305', 'name' => 'شركات شقيقة', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020305001', 'name' => 'شركة لاكشوريا إيليت ليموزين', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020305002', 'name' => 'شركة الطريق المتميز', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '0102020305003', 'name' => 'شركة لاكشوريا ايليت لغسيل السيارات', 'type' => Account::CURRENT_ASSET],
            ['arabic_code' => '01020204', 'name' => 'حـ / ذمم محصلين مبيعات', 'type' => Account::RECEIVABLE],
            ['arabic_code' => '0102020401', 'name' => 'محصلين المبيعات', 'type' => Account::RECEIVABLE],
            
            // ============ الالتزامات (02 - Liabilities) ============
            
            // 02: الالتزامات (Main Liabilities Category)
            ['arabic_code' => '02', 'name' => 'الالتزامات', 'type' => Account::CURRENT_LIABILITY],
            
            // 0201: الالتزامات قصيرة الأجل (Current Liabilities)
            ['arabic_code' => '0201', 'name' => 'حـ / الالتزامات قصيرة الاجل', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '020101', 'name' => 'حـ / الدائنون', 'type' => Account::PAYABLE],
            ['arabic_code' => '02010101', 'name' => 'حـ / الموردين', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010101', 'name' => 'موردين الخدمات', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010101001', 'name' => 'شركة ديبوزيل لللدعاية والاعلان', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010101002', 'name' => 'شركة وان كلايك دريف للدعاية والاعلان', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010101003', 'name' => 'شركة انسايد لخدمات متابعة المعاملت ش.ذ.م.م', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010101004', 'name' => 'شركة مسار التطوير لطباعة وتصوير المستندات', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010102', 'name' => 'حـ / موردين الاصول الثابتة', 'type' => Account::PAYABLE],
            ['arabic_code' => '0201010103', 'name' => 'ح/ موردين سيارات', 'type' => Account::PAYABLE],
            ['arabic_code' => '020102', 'name' => 'حـ / الأرصدة الدائنة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '02010201', 'name' => 'حـ / المصروفات والالتزامات المستحقة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101', 'name' => 'حـ / رواتب مستحقة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001', 'name' => 'رواتب مستحقة شركة لاكشوريا', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001001', 'name' => 'رواتب مستحقة عماد فؤاد', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001002', 'name' => 'رواتب مستحقة عدنان', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001003', 'name' => 'رواتب مستحقة ماري', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001004', 'name' => 'رواتب مستحقة احمد اسامة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001005', 'name' => 'رواتب مستحقة مصطفي الخولي', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001006', 'name' => 'رواتب مستحقة محمد رفعت', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001007', 'name' => 'رواتب مستحقة محمد حمادة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001008', 'name' => 'رواتب مستحقة محمد عمر', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001009', 'name' => 'رواتب مستحقة بلال', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001010', 'name' => 'رواتب مستحقة نايب الرحمن', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001011', 'name' => 'رواتب مستحقة ريمان', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001012', 'name' => 'رواتب مستحقة احمد الحمصاني', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101001013', 'name' => 'رواتب مستحقة عبدالغني عاطف', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020101002', 'name' => 'رواتب مستحقة عمالة تحت التجربة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020102', 'name' => 'التزامات مؤقتة - بوابة سالك', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020103', 'name' => 'التزامات مؤقتة - بوابة درب', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020104', 'name' => 'التزامات مؤقتة - مخالفات مروربة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020105', 'name' => 'حـ / مصروفات اخرى مستحقة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020105001', 'name' => 'ح/ مصروف مستحق تامين سيارات', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020105002', 'name' => 'ح/ مصروف مستحق تامين طبي', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020105003', 'name' => 'ح/ مصروف مستحق ايجار مكتب', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020106', 'name' => 'التزامات مؤقتة - اخري', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020107', 'name' => 'التزامات تامينات مستردة للغير', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020108', 'name' => 'الالتزامات المستحقة لشركة كاردو', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020109', 'name' => 'عمولة مبيعات استعارات السيارات', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '02010202', 'name' => 'حـ / الأيرادات المقدمة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '02010203', 'name' => 'حـ / المخصصات', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020301', 'name' => 'حـ / المخصصات الادارية', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020301001', 'name' => 'مخصص بدل الاجازة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020301002', 'name' => 'مخصص بدل التذاكر', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020302', 'name' => 'حـ / المخصصات النظامية', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020302001', 'name' => 'ح/ مخصص ديون مشكوك فيها', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020302002', 'name' => 'ح/ مخصص سداد الضريبة علي الدخل', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020302003', 'name' => 'ح/ مخصص سداد قيمة المضافة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020302004', 'name' => 'ح/ مخصص التامينات الاجتماعية', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020303', 'name' => 'حـ / مخصصات الأهلاك', 'type' => Account::CONTRA_ASSET],
            ['arabic_code' => '0201020303001', 'name' => 'ح/ مخصص اهلاك السيارات', 'type' => Account::CONTRA_ASSET],
            ['arabic_code' => '0201020303002', 'name' => 'ح/ مخصص اهلاك الاجهزة', 'type' => Account::CONTRA_ASSET],
            ['arabic_code' => '0201020303003', 'name' => 'مخصص اهلاك البرامج و المواقع الالكتروني', 'type' => Account::CONTRA_ASSET],
            ['arabic_code' => '02010204', 'name' => 'حـ / الضرائب المحصلة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020401', 'name' => 'ح/ ضريبة محصلة القيمة المضافة 5%', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020402', 'name' => 'ح/ ضريبة محصلة القيمة المضافة 5%  عجمان', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '02010205', 'name' => 'حـ / أرصدة دائنة أخرى', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020501', 'name' => 'حـ / التسويات', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020501001', 'name' => 'ح/ تسويات ضريبة قيمة مضافة', 'type' => Account::CURRENT_LIABILITY],
            ['arabic_code' => '0201020501002', 'name' => 'ح/ التسويات الجردية', 'type' => Account::CURRENT_LIABILITY],
            
            // 0202: الالتزامات طويلة الأجل (Long-term Liabilities)
            ['arabic_code' => '0202', 'name' => 'حـ / الالتزامات طويلة الاجل', 'type' => Account::NON_CURRENT_LIABILITY],
            ['arabic_code' => '020201', 'name' => 'حـ / قروض طويلة الاجل', 'type' => Account::NON_CURRENT_LIABILITY],
            
            // ============ رأس المال (03 - Equity) ============
            
            // 03: رأس المال وحقوق الملكية (Capital & Equity)
            ['arabic_code' => '03', 'name' => 'رأس المال وحقوق الملكيه', 'type' => Account::EQUITY],
            ['arabic_code' => '0301', 'name' => 'حـ / رأس المال', 'type' => Account::EQUITY],
            ['arabic_code' => '030101', 'name' => 'حـ / رأس المال العام', 'type' => Account::EQUITY],
            ['arabic_code' => '03010101', 'name' => 'حـ / رأس مال الشريك فهد الزير..', 'type' => Account::EQUITY],
            ['arabic_code' => '03010102', 'name' => 'حـ / رأس مال الشريك محمد عيسي', 'type' => Account::EQUITY],
            ['arabic_code' => '030102', 'name' => 'حـ / رأس المال المال المضاف', 'type' => Account::EQUITY],
            ['arabic_code' => '0302', 'name' => 'حـ / حقوق الملكية', 'type' => Account::EQUITY],
            ['arabic_code' => '030201', 'name' => 'حـ / جاري الشركاء', 'type' => Account::EQUITY],
            ['arabic_code' => '03020101', 'name' => 'جاري المالك محمد عيسي', 'type' => Account::EQUITY],
            ['arabic_code' => '03020102', 'name' => 'جاري المالك فهد الزير', 'type' => Account::EQUITY],
            ['arabic_code' => '0303', 'name' => 'حـ / الأحتياطيات', 'type' => Account::EQUITY],
            ['arabic_code' => '030301', 'name' => 'حـ / الأحتياطيات المباشرة', 'type' => Account::EQUITY],
            ['arabic_code' => '03030101', 'name' => 'حـ / الأحتياطي القانوني', 'type' => Account::EQUITY],
            ['arabic_code' => '03030102', 'name' => 'حـ / الأحتياطي النظامي', 'type' => Account::EQUITY],
            ['arabic_code' => '030302', 'name' => 'حـ / الأحتياطيات الغير المباشرة', 'type' => Account::EQUITY],
            ['arabic_code' => '03030201', 'name' => 'حـ / أحتياطي التوسعات', 'type' => Account::EQUITY],
            ['arabic_code' => '0304', 'name' => 'حـ / الأرباح والخسائر', 'type' => Account::EQUITY],
            ['arabic_code' => '030401', 'name' => 'الأرباح والخسائر المرحله', 'type' => Account::EQUITY],
            
            // ============ الايرادات (04 - Revenue) ============
            
            // 04: الايرادات (Main Revenue Category)
            ['arabic_code' => '04', 'name' => 'الايرادات', 'type' => Account::OPERATING_REVENUE],
            
            // 0401: ايرادات النشاط (Operating Revenue)
            ['arabic_code' => '0401', 'name' => 'حـ /  ايرادات النشاط', 'type' => Account::OPERATING_REVENUE],
            ['arabic_code' => '040101', 'name' => 'حـ / أيرادات المبيعات', 'type' => Account::OPERATING_REVENUE],
            ['arabic_code' => '04010101', 'name' => 'حـ / صافي المبيعات', 'type' => Account::OPERATING_REVENUE],
            ['arabic_code' => '0401010101', 'name' => 'حـ / المبيعات النقدية', 'type' => Account::OPERATING_REVENUE],
            ['arabic_code' => '0401010102', 'name' => 'حـ / المبيعات الاجلة', 'type' => Account::OPERATING_REVENUE],
            
            // 0402: ايرادات متنوعة (Other Revenue)
            ['arabic_code' => '0402', 'name' => 'حـ / ايرادات متنوعة', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040201', 'name' => 'ح/ الخصم المكتسب', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040202', 'name' => 'جزاءات موظفين', 'type' => Account::NON_OPERATING_REVENUE],
            
            // 0403: ايرادات اخري (Other Revenue)
            ['arabic_code' => '0403', 'name' => 'ايرادات اخري', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040301', 'name' => 'ايرادات عقود محجوزة', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040302', 'name' => 'ايرادات بوابة سالك', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040303', 'name' => 'ايرادات مقابل اضرار', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040304', 'name' => 'ايردات بترول', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040305', 'name' => 'ايردات بوابة درب', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040306', 'name' => 'ايرادات مخالفات مرورية', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040307', 'name' => 'ايردات غسيل سيارة', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040308', 'name' => 'ايردات توصيل', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040309', 'name' => 'ايرادات محصلة من شركة التامين', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040310', 'name' => 'عقود فترة اغلاق برنامج التاجير', 'type' => Account::NON_OPERATING_REVENUE],
            ['arabic_code' => '040311', 'name' => 'عقود فترة تفعيل برنامج التشغيل', 'type' => Account::NON_OPERATING_REVENUE],
            
            // ============ المصروفات (05 - Expenses) ============
            
            // 05: المصروفات (Main Expenses Category)
            ['arabic_code' => '05', 'name' => 'المصروفات', 'type' => Account::OPERATING_EXPENSE],
            
            // 0501: تكلفة البضاعة المباعة (Cost of Goods Sold)
            ['arabic_code' => '0501', 'name' => 'حـ / تكلفة البضاعة المباعة', 'type' => Account::DIRECT_EXPENSE],
            ['arabic_code' => '050101', 'name' => 'حـ / صافي المشتريات', 'type' => Account::DIRECT_EXPENSE],
            ['arabic_code' => '05010101', 'name' => 'حـ / المشتريات النقدية', 'type' => Account::DIRECT_EXPENSE],
            ['arabic_code' => '05010102', 'name' => 'حـ / المشتريات الأجلة', 'type' => Account::DIRECT_EXPENSE],
            
            // 0502: المصروفات العمومية والادارية (General & Administrative Expenses)
            ['arabic_code' => '0502', 'name' => 'حـ / المصروفات العمومية والادارية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050201', 'name' => 'حـ / المصروفات العمومية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020101', 'name' => 'حـ / الرواتب', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010101', 'name' => 'حـ / الراتب الأساسي', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010102', 'name' => 'حـ / بدل السكن', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010103', 'name' => 'حـ / بدل الأنتقال', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010104', 'name' => 'حـ / بدل الاتصال', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010105', 'name' => 'حـ / بدل وقت أضافي', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010106', 'name' => 'رواتب عمالة خارجية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020102', 'name' => 'حـ / الرسوم والاشتراكات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010201', 'name' => 'حـ/ رسوم حكومية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010202', 'name' => 'التأمينات االجتماعية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010203', 'name' => 'تامين السيارات.', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020103', 'name' => 'حـ / حجوزات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020104', 'name' => 'حـ / مصروفات موظفين', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010401', 'name' => 'حـ / مصروفات علاج موظفين', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020105', 'name' => 'حـ / الفواتير الشهرية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010501', 'name' => 'حـ / فواتير الكهرباء', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010502', 'name' => 'حـ / فواتير المياه', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010503', 'name' => 'حـ / فواتير الهاتف', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010504', 'name' => 'حـ /  فواتير الجوال', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010505', 'name' => 'حـ / فواتير الانترنت', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020106', 'name' => 'حـ / مصروفات الصيانة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010601', 'name' => 'حـ / صيانة الحاسب', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010602', 'name' => 'حـ / صيانة عامة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010602001', 'name' => 'تجهيزات مكتب', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010602002', 'name' => 'صيانة الاجهزة والالات المكتبية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010602003', 'name' => 'صيانة دورية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502010603', 'name' => 'صيانة السيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020107', 'name' => 'تجهيز سكن الموظفين', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050202', 'name' => 'حـ / المصروفات البيعية والتسويقية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020201', 'name' => 'حـ / الدعاية والاعلان', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020202', 'name' => 'حـ / عمولات مبيعات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020203', 'name' => 'مصاريف استعارة السيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020204', 'name' => 'مصاريف مصور خارجي', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020205', 'name' => 'مصاريف بلوجرز - دعاية واعلان', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020206', 'name' => 'مكافأة ادارية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050203', 'name' => 'حـ /  التأشيرات والاقامات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020301', 'name' => 'حـ / رسوم اصدار تاشيرة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020302', 'name' => 'حـ / رسوم الجوازات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020303', 'name' => 'حـ / رسوم مكتب العمل', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020304', 'name' => 'رسوم اصدار اقامات وهوية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050204', 'name' => 'حـ / الايجارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020401', 'name' => 'ايجار المكتب والفروع', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020402', 'name' => 'ايجار السكن', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020403', 'name' => 'ايجار مواقف للسيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050205', 'name' => 'حـ / مصروفات الضريبية علي الدخل', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020501', 'name' => 'حـ / مصروف الضريبية علي الدخل', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050206', 'name' => 'حـ / مصروفات الاهلاك و أطفاء مصروفات التأسيس', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020601', 'name' => 'حـ / مصروفات الاهلاك', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020602', 'name' => 'حـ / مصروف أهلاك الأثاث', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020603', 'name' => 'حـ / مصروف أهلاك السيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020604', 'name' => 'حـ / مصروف أهلاك الأجهزة و الحاسب', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020605', 'name' => 'حـ / مصروف اهلاك البرامج و المواقع الالكترونية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050207', 'name' => 'حـ / مصروفات السيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020701', 'name' => 'حـ / مصروف تأمين و تجديد استمارة سيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020702', 'name' => 'حـ / مصروفات المخالفات المرورية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020703', 'name' => 'حـ / محروقات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020704', 'name' => 'حـ / غسيل سيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020705', 'name' => 'موقف سيارات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050208', 'name' => 'حـ / مصروفات نثريات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020801', 'name' => 'حـ / ضيافة ونظافة وبوفية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020802', 'name' => 'حـ / قرطاسية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020803', 'name' => 'حـ / مطبوعات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050209', 'name' => 'حـ / مصروفات المخصصات', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020901', 'name' => 'حـ / مصروف مخصص تذاكر سفر', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05020902', 'name' => 'حـ / مصروف مخصص بدل اجازة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050210', 'name' => 'حـ / مصروفات بنكية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021001', 'name' => 'حـ / مصروفات بنكية بنك WIO', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021002', 'name' => 'مصروفات بنكية بنك المشرق', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021003', 'name' => 'حـ / مصروفات اصدار شيكات بنك WIO', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021004', 'name' => 'حـ / مصروفات اصدار شيكات بنك المشرق', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050211', 'name' => 'حـ / الحملات الاعلانية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050212', 'name' => 'حـ / مصاريف عامة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021201', 'name' => 'مصاريف متنوعة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021202', 'name' => 'حـ / أتعاب تدقيق و مراجعة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021203', 'name' => 'حـ / فروق الكسور عشرية', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021204', 'name' => 'مصروفات الباركين - لاموزين', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '050213', 'name' => 'مصاريف تشغيل مكتب دبي  وعجمان', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021301', 'name' => 'مصاريف مكتب دبي', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502130101', 'name' => 'محروقات سيارات الخدمة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502130102', 'name' => 'مصروفات مكتب دبي', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502130103', 'name' => 'مصروف بوابات سالك سيارة الخدمة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '0502130104', 'name' => 'مصاريف صيانة سيارة الخدمة', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021302', 'name' => 'مصاريف مكتب عجمان', 'type' => Account::OPERATING_EXPENSE],
            ['arabic_code' => '05021303', 'name' => 'مصاريف مكتب الصاجعة', 'type' => Account::OPERATING_EXPENSE],
        ];
        
        $createdCount = 0;
        
        foreach ($accounts as $accountData) {
            $code = $this->convertArabicCodeToInteger($accountData['arabic_code']);
            
            $existingAccount = Account::where('entity_id', $entity->id)
                ->where('code', $code)
                ->first();
                
            if (!$existingAccount) {
                Account::create([
                    'name' => $accountData['name'],
                    'account_type' => $accountData['type'],
                    'code' => $code,
                    'currency_id' => $currency->id,
                    'entity_id' => $entity->id,
                ]);
                
                $createdCount++;
                $this->command->line("   Created: {$accountData['name']} ({$accountData['arabic_code']} → {$code})");
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
