# Daily Revenue Recognition Implementation

## Overview

This implementation adds IFRS-compliant daily revenue and VAT recognition for rental contracts. Revenue checks now run hourly and respect business timing rules (3-hour post-start grace period, 1-hour buffer after each 24-hour cycle) while following accrual accounting principles and UAE VAT regulations.

## What Was Implemented

### 1. Revenue & VAT Recognition Command
**File**: `app/Console/Commands/RecognizeContractRevenue.php`

A Laravel artisan command that:
- Runs every hour (scheduled in `routes/console.php`) using Dubai time
- Processes all active contracts
- Calculates eligible billable days using a 3-hour initial grace period and a 1-hour buffer for subsequent days
- Splits daily amounts into net rental and VAT based on contract's `is_vat_inclusive` flag
- Checks how many days have already been recognized for both revenue and VAT
- Creates separate IFRS journal entries for revenue and VAT per unrecognized day
- Supports manual execution: `php artisan contracts:recognize-revenue`
- Supports testing specific contracts: `php artisan contracts:recognize-revenue --contract_id=xxx`

### 2. Accounting Service Methods
**File**: `app/Services/AccountingService.php`

Added two methods for daily recognition:

**`recordDailyRevenueRecognition()`** - Recognizes net rental revenue:
- Creates IFRS journal entries with proper double-entry bookkeeping
- **Debit**: Customer Deposits (Liability) - reduces what we owe the customer
- **Credit**: Rental Income (Revenue) - recognizes revenue
- Uses branch-specific GL accounts from Quick Pay mappings if available
- Falls back to default accounts if not configured
- Logs all transactions for audit trail

**`recordDailyVATRecognition()`** - Recognizes VAT liability:
- Creates IFRS journal entries for VAT recognition
- **Debit**: VAT Collection (Liability) - reduces temporary holding
- **Credit**: VAT Payable (Liability) - official liability to tax authority
- Uses branch-specific GL accounts from Quick Pay mappings if available
- Falls back to default accounts if not configured
- Logs all transactions for audit trail

### 3. Scheduler Configuration
**File**: `routes/console.php`

Added hourly schedule:
```php
Schedule::command('contracts:recognize-revenue')->hourly()->timezone('Asia/Dubai');
```

### 4. VAT Configuration
**Files**: Multiple

Added `is_vat_inclusive` field to contracts:
- **Database**: Migration adds boolean field to contracts table (defaults to `true`)
- **Model**: Added to `Contract` fillable and casts
- **Frontend**: Checkbox in Create Contract form (Pricing tab)
- **Controller**: Validates and saves the field during contract creation

### 5. Quick Pay Integration
**File**: `app/Http/Controllers/ContractController.php`

Updated Quick Pay summary calculation (`getQuickPaySummary` method):
- Splits consumed rental amount into net rental and VAT
- **If VAT-inclusive**: Calculates backwards (e.g., 105 AED → 100 AED net + 5 AED VAT)
- **If VAT-exclusive**: Calculates forwards (e.g., 100 AED net + 5 AED VAT → 105 AED)
- Updates both `rental_income` and `vat_collection` rows with separate totals
- Tracks paid amounts separately for each

### 6. Translation Keys
**Files**: `lang/en/words.php`, `lang/ar/words.php`, and `resources/js/lib/i18n.ts`

Added:
- `revenue_recognition` - "Revenue Recognition" / "الاعتراف بالإيرادات"
- `daily_revenue_recognized` - "Daily Revenue Recognized" / "الإيرادات اليومية المعترف بها"
- `price_is_vat_inclusive` - "Price is VAT-inclusive" / "السعر شامل ضريبة القيمة المضافة"
- `vat_inclusive_explanation` - Explanation of VAT-inclusive pricing
- `vat_exclusive_explanation` - Explanation of VAT-exclusive pricing

## How It Works

### Example Scenario 1: VAT-Inclusive Contract

**Contract Details:**
- Duration: 2 days (Oct 16-17, 2025)
- Daily Rate: 105 AED (VAT-inclusive)
- Is VAT Inclusive: ✅ Yes
- Net Daily Rate: 100 AED
- Daily VAT: 5 AED
- Total: 210 AED

**Timeline:**

**Oct 15, 4:00 PM** - Customer pays 210 AED deposit via Quick Pay:
```
Dr: Cash                          210 AED
Cr: Customer Deposits (Liability) 200 AED  (Net rental)
Cr: VAT Collection (Liability)     10 AED  (VAT collected)
```

**Oct 15, 7:00 PM** *(3 hours after start)* - First day recognition:
```
Revenue Recognition:
Dr: Customer Deposits (Liability) 100 AED
Cr: Rental Income (Revenue)       100 AED
Narration: "Revenue recognition for Contract CON-001234 - Day 1"

VAT Recognition:
Dr: VAT Collection (Liability)      5 AED
Cr: VAT Payable (Liability)         5 AED
Narration: "VAT recognition for Contract CON-001234 - Day 1"
```

**Oct 16, 4:00 PM** *(24h + 1h buffer after start)* - Second day recognition:
```
Revenue Recognition:
Dr: Customer Deposits (Liability) 100 AED
Cr: Rental Income (Revenue)       100 AED
Narration: "Revenue recognition for Contract CON-001234 - Day 2"

VAT Recognition:
Dr: VAT Collection (Liability)      5 AED
Cr: VAT Payable (Liability)         5 AED
Narration: "VAT recognition for Contract CON-001234 - Day 2"
```

### Example Scenario 2: VAT-Exclusive Contract

**Contract Details:**
- Duration: 2 days (Oct 16-17, 2025)
- Daily Rate: 100 AED (VAT-exclusive)
- Is VAT Inclusive: ❌ No
- Net Daily Rate: 100 AED
- Daily VAT: 5 AED (calculated separately)
- Total with VAT: 210 AED

**Timeline:**

Same journal entries as VAT-inclusive, but calculation method differs:
- VAT-inclusive: 105 AED ÷ 1.05 = 100 AED net
- VAT-exclusive: 100 AED × 1.05 = 105 AED total

### Key Accounting Principles

1. **Accrual Accounting**: Revenue and VAT are recognized when earned (service consumed), not when cash is received
2. **IFRS Compliance**: Follows IFRS 15 revenue recognition standards
3. **UAE VAT Compliance**: Follows Federal Tax Authority (FTA) regulations for VAT recognition
4. **Separate Concerns**: Customer payments (Quick Pay) are separate from revenue/VAT recognition
5. **Audit Trail**: Each day gets separate IFRS transactions for revenue and VAT for clear tracking
6. **Two-Stage VAT Recognition**: 
   - Stage 1: Customer payment → VAT Collection (temporary holding)
   - Stage 2: Service consumption → VAT Payable (official liability to FTA)

## Quick Pay Integration

The Quick Pay modal now shows rental income and VAT separately:

**Rental Income Row:**
- **Total Column**: Shows consumed net rental amount (days elapsed × daily net rate)
- **Paid Column**: Shows customer prepayments allocated to rental income
- **Remaining Column**: Shows net rental amount customer still owes

**VAT Collection Row:**
- **Total Column**: Shows consumed VAT amount (days elapsed × daily VAT)
- **Paid Column**: Shows customer prepayments allocated to VAT
- **Remaining Column**: Shows VAT amount customer still owes

The daily revenue and VAT recognition happens in the background and doesn't affect the Quick Pay UI directly. It's purely an accounting function to properly recognize revenue and VAT in the IFRS system.

## GL Account Configuration

The system uses the following accounts:

### Customer Deposits (Liability)
- **Primary Source**: Branch Quick Pay mappings (`quick_pay_accounts['liability']['rental_income']`)
- **Fallback**: "Customer Deposits - Unearned Revenue" (Code: 2102)
- **Purpose**: Temporary holding for customer prepayments until service is consumed

### Rental Income (Revenue)
- **Account**: "Rental Revenue" (Code: 4001)
- **Type**: Operating Revenue
- **Purpose**: Recognized revenue as service is consumed

### VAT Collection (Liability)
- **Primary Source**: Branch Quick Pay mappings (`quick_pay_accounts['liability']['vat_collection']`)
- **Fallback**: "VAT Collection" (Code: 2103)
- **Purpose**: Temporary holding for VAT collected from customer payments

### VAT Payable (Liability)
- **Account**: "VAT Payable" or "Output VAT" (Code: 2200)
- **Type**: Current Liability
- **Purpose**: Official liability to Federal Tax Authority (FTA) for VAT on recognized revenue

## Testing

### Manual Test
```bash
# Test on a specific contract
php artisan contracts:recognize-revenue --contract_id={contract_uuid}

# Run for all active contracts
php artisan contracts:recognize-revenue
```

### Verify Results
1. Check IFRS transactions table for new journal entries
2. Look for two types of narrations:
   - "Revenue recognition for Contract {contract_number} - Day X"
   - "VAT recognition for Contract {contract_number} - Day X"
3. Verify line items show correct debit/credit entries for both revenue and VAT
4. Check Quick Pay summary displays both rental income and VAT Collection correctly
5. Verify VAT Payable account balance increases appropriately

### Example Log Output
```
🎯 Starting daily revenue recognition for active contracts...

Found 3 active contract(s) to process.

✅ Contract CON-001234: Revenue: 1 day(s) = AED 100.00, VAT: 1 day(s) = AED 5.00
✅ Contract CON-001235: Revenue: 2 day(s) = AED 240.00, VAT: 2 day(s) = AED 12.00
⏭️  Contract CON-001236: Revenue already recognized for all elapsed days

📊 Summary:
   - Processed: 2 contract(s)
   - Skipped: 1 contract(s)
   - Errors: 0 contract(s)
   - Total Revenue Recognized: AED 340.00
   - Total VAT Recognized: AED 17.00

✨ Revenue recognition completed successfully!
```

## Important Notes

1. **Time Zone**: All calculations use Dubai timezone (Asia/Dubai)
2. **Calendar Days**: Recognition is based on calendar days, not hours
3. **Idempotent**: Safe to run multiple times - won't duplicate entries
4. **VAT Configuration**: Each contract stores whether its price is VAT-inclusive or exclusive
5. **Separate Tracking**: Revenue and VAT days are tracked independently
6. **Background Process**: Revenue and VAT recognition is automatic via scheduler
7. **Audit Trail**: Each transaction (revenue and VAT) has a unique narration for tracking
8. **Quick Pay Update**: Now displays rental income and VAT Collection as separate rows

## Troubleshooting

### Check if command is registered
```bash
php artisan list | grep revenue
```

### Check scheduler status
```bash
php artisan schedule:list
```

### View logs
```bash
tail -f storage/logs/laravel.log | grep "Revenue recognition"
```

### Manual test on specific date
You can temporarily modify the `$now` variable in the command to test different scenarios.

## Files Modified

### Backend
1. ✅ `app/Console/Commands/RecognizeContractRevenue.php` - Extended with VAT recognition
2. ✅ `app/Services/AccountingService.php` - Added recordDailyVATRecognition() and helper methods
3. ✅ `app/Http/Controllers/ContractController.php` - Updated Quick Pay to split rental/VAT
4. ✅ `app/Models/Contract.php` - Added is_vat_inclusive field
5. ✅ `routes/console.php` - Hourly schedule with Dubai timezone
6. ✅ `database/migrations/..._add_is_vat_inclusive_to_contracts_table.php` - New migration

### Frontend
7. ✅ `resources/js/pages/Contracts/Create.vue` - Added VAT-inclusive checkbox
8. ✅ `resources/js/lib/i18n.ts` - Added translations

### Translations
9. ✅ `lang/en/words.php` - Added VAT-related translations
10. ✅ `lang/ar/words.php` - Added VAT-related translations

## Next Steps

1. Test with real contracts in development
2. Verify IFRS reports show correct revenue and VAT recognition
3. Verify Quick Pay displays rental income and VAT separately
4. Monitor scheduler execution logs for both revenue and VAT transactions
5. Verify VAT Payable account accumulates correctly for FTA reporting
6. Consider adding a dashboard widget showing daily recognized revenue and VAT
7. Train staff on the new VAT-inclusive/exclusive contract option

