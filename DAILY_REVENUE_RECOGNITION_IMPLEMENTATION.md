# Daily Revenue Recognition Implementation

## Overview

This implementation adds IFRS-compliant daily revenue recognition for rental contracts. Revenue is now recognized daily at 3 AM Dubai time as the rental service is consumed, following proper accrual accounting principles.

## What Was Implemented

### 1. Revenue Recognition Command
**File**: `app/Console/Commands/RecognizeContractRevenue.php`

A Laravel artisan command that:
- Runs daily at 3 AM Dubai time (scheduled in `routes/console.php`)
- Processes all active contracts
- Calculates days elapsed from contract start date
- Checks how many days have already been recognized (via IFRS transaction narration)
- Creates one IFRS journal entry per unrecognized day
- Supports manual execution: `php artisan contracts:recognize-revenue`
- Supports testing specific contracts: `php artisan contracts:recognize-revenue --contract_id=xxx`

### 2. Accounting Service Method
**File**: `app/Services/AccountingService.php`

Added `recordDailyRevenueRecognition()` method that:
- Creates IFRS journal entries with proper double-entry bookkeeping
- **Debit**: Customer Deposits (Liability) - reduces what we owe the customer
- **Credit**: Rental Income (Revenue) - recognizes revenue
- Uses branch-specific GL accounts from Quick Pay mappings if available
- Falls back to default accounts if not configured
- Logs all transactions for audit trail

### 3. Scheduler Configuration
**File**: `routes/console.php`

Added daily schedule:
```php
Schedule::command('contracts:recognize-revenue')->dailyAt('03:00')->timezone('Asia/Dubai');
```

### 4. Translation Keys
**Files**: `lang/en/words.php` and `lang/ar/words.php`

Added:
- `revenue_recognition` - "Revenue Recognition" / "الاعتراف بالإيرادات"
- `daily_revenue_recognized` - "Daily Revenue Recognized" / "الإيرادات اليومية المعترف بها"

## How It Works

### Example Scenario

**Contract Details:**
- Duration: 2 days (Oct 16-17, 2025)
- Daily Rate: 100 AED
- Total: 200 AED

**Timeline:**

**Oct 15, 4:00 PM** - Customer pays 200 AED deposit via Quick Pay:
```
Dr: Cash                          200 AED
Cr: Customer Deposits (Liability) 200 AED
```

**Oct 17, 3:00 AM** - First revenue recognition (Day 1):
```
Dr: Customer Deposits (Liability) 100 AED
Cr: Rental Income (Revenue)       100 AED
Narration: "Revenue recognition for Contract CON-001234 - Day 1"
```

**Oct 18, 3:00 AM** - Second revenue recognition (Day 2):
```
Dr: Customer Deposits (Liability) 100 AED
Cr: Rental Income (Revenue)       100 AED
Narration: "Revenue recognition for Contract CON-001234 - Day 2"
```

### Key Accounting Principles

1. **Accrual Accounting**: Revenue is recognized when earned (service consumed), not when cash is received
2. **IFRS Compliance**: Follows IFRS 15 revenue recognition standards
3. **Separate Concerns**: Customer payments (Quick Pay) are separate from revenue recognition
4. **Audit Trail**: Each day gets its own IFRS transaction for clear tracking

## Quick Pay Integration

The Quick Pay modal continues to work as before:

- **Total Column**: Shows consumed amount (days elapsed × daily rate)
- **Paid Column**: Shows customer prepayments allocated to rental income
- **Remaining Column**: Shows what customer still owes

The daily revenue recognition happens in the background and doesn't affect the Quick Pay UI. It's purely an accounting function to properly recognize revenue in the IFRS system.

## GL Account Configuration

The system uses the following accounts:

### Customer Deposits (Liability)
- **Primary Source**: Branch Quick Pay mappings (`quick_pay_accounts['liability']['rental_income']`)
- **Fallback**: "Customer Deposits - Unearned Revenue" (Code: 2102)

### Rental Income (Revenue)
- **Account**: "Rental Revenue" (Code: 4001)
- **Type**: Operating Revenue

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
2. Look for narration: "Revenue recognition for Contract {contract_number} - Day X"
3. Verify line items show correct debit/credit entries
4. Check Quick Pay summary still displays correctly

### Example Log Output
```
🎯 Starting daily revenue recognition for active contracts...

Found 3 active contract(s) to process.

✅ Contract CON-001234: Recognized 1 day(s) = AED 100.00
✅ Contract CON-001235: Recognized 2 day(s) = AED 240.00
⏭️  Contract CON-001236: Revenue already recognized for all elapsed days

📊 Summary:
   - Processed: 2 contract(s)
   - Skipped: 1 contract(s)
   - Errors: 0 contract(s)
   - Total Revenue Recognized: AED 340.00

✨ Revenue recognition completed successfully!
```

## Important Notes

1. **Time Zone**: All calculations use Dubai timezone (Asia/Dubai)
2. **Calendar Days**: Recognition is based on calendar days, not hours
3. **Idempotent**: Safe to run multiple times - won't duplicate entries
4. **No UI Changes**: Quick Pay modal works exactly as before
5. **Background Process**: Revenue recognition is automatic via scheduler
6. **Audit Trail**: Each transaction has a unique narration for tracking

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
You can temporarily modify the `$today` variable in the command to test different scenarios.

## Files Modified

1. ✅ `app/Console/Commands/RecognizeContractRevenue.php` (NEW)
2. ✅ `app/Services/AccountingService.php` (Added method)
3. ✅ `routes/console.php` (Added schedule)
4. ✅ `lang/en/words.php` (Added translations)
5. ✅ `lang/ar/words.php` (Added translations)

## Next Steps

1. Test with real contracts in development
2. Verify IFRS reports show correct revenue recognition
3. Monitor scheduler execution logs
4. Consider adding a dashboard widget showing daily recognized revenue

