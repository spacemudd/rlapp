# Additional Fees VAT Split Implementation

## Implementation Date
October 15, 2025

## Problem Statement
Previously, when adding additional fees with VAT (e.g., 100 AED + 5 AED VAT = 105 AED) and processing payment through Quick Pay, the system incorrectly credited the full 105 AED to a single "Additional Fees" account. This violated IFRS accounting principles.

## Solution
Modified Quick Pay to display and process additional fees as **two separate line items**:
1. **Subtotal** - The base fee amount (100 AED) → Additional Fees (Liability/Deferred Revenue)
2. **VAT** - The VAT amount (5 AED) → VAT Collection (تحصيل الضريبة القيمة المضافة - Liability)

## IFRS Compliant Accounting Treatment

### Before (Incorrect)
```
Debit:  Cash                  105
Credit: Additional Fees       105
```

### After (Correct) - At Payment Time via Quick Pay
```
Debit:  Cash                           105
Credit: Additional Fees (Liability)    100
Credit: VAT Collection (Liability)       5
```

### Later - At Invoice Time (Contract Closure)
```
Debit:  Additional Fees               100
Credit: Revenue                       100

Debit:  VAT Collection                  5
Credit: VAT Payable                     5
```

## Implementation Details

### 1. Translation Keys Added

**Files Modified:**
- `lang/en/words.php`
- `lang/ar/words.php`
- `resources/js/lib/i18n.ts`

**New Translation:**
- English: `'vat_for_fee' => 'VAT - :fee'`
- Arabic: `'vat_for_fee' => 'ضريبة القيمة المضافة - :fee'`

### 2. Backend Changes

**File:** `app/Http/Controllers/ContractController.php`
**Method:** `getQuickPaySummary()`
**Lines:** 878-963

#### Query Modification
Simplified query - no need for vat_account_id:
```php
->selectRaw('
    fee_type, 
    SUM(subtotal) as total_subtotal, 
    SUM(vat_amount) as total_vat, 
    SUM(total) as total_amount
')
```

All VAT amounts use the same VAT Collection account from branch configuration `quick_pay_accounts['liability']['vat_collection']`.

#### Row Creation Logic
For each fee type, the system now creates **two rows** (if applicable):

**Subtotal Row:**
- Row ID: `additional_fee_{fee_type}`
- Description: Fee type name (e.g., "Car Wash")
- Amount: Subtotal only (100 AED)
- GL Account: From branch configuration `quick_pay_accounts['liability']['additional_fees']`
- Tracks paid/remaining amounts separately

**VAT Row:**
- Row ID: `additional_fee_{fee_type}_vat`
- Description: "VAT - {fee type name}" (localized)
- Amount: VAT only (5 AED)
- GL Account: VAT Collection account from branch configuration `quick_pay_accounts['liability']['vat_collection']`
- Tracks paid/remaining amounts separately
- **Important**: All VAT rows use the SAME VAT Collection liability account

#### Paid Amount Calculation
Each row (subtotal and VAT) has its own paid amount calculation by querying `PaymentReceiptAllocation` for its specific `row_id`.

### 3. Accounting Service
**File:** `app/Services/AccountingService.php`
**Method:** `recordPaymentReceipt()`

No changes required. The existing logic correctly:
- Creates separate IFRS line items for each allocation
- Uses the `gl_account_id` from each row
- Credits the appropriate GL account (Revenue or VAT Payable)

## User Experience

### Quick Pay Modal Display
When opening Quick Pay for a contract with additional fees:

**Before:**
```
Liability Section:
- Car Wash: 105.00 AED
```

**After:**
```
Liability Section:
- Car Wash: 100.00 AED
- VAT - Car Wash: 5.00 AED
```

### Payment Allocation
Users can now:
- Allocate payments separately to the fee and its VAT
- See individual paid/remaining amounts for each component
- Track revenue and VAT collections independently

## Example Scenario

### Step 1: Add Additional Fee
- Fee Type: Car Wash
- Quantity: 1
- Unit Price: 100 AED
- Discount: 0
- VAT: Not exempt
- **Result:** Subtotal = 100, VAT = 5, Total = 105

### Step 2: View in Quick Pay
Two rows appear:
1. "غسيل سيارات" (Car Wash) - 100.00 AED
2. "ضريبة القيمة المضافة - غسيل سيارات" (VAT - Car Wash) - 5.00 AED

### Step 3: Allocate Payment
User enters payment of 105 AED and allocates:
- 100 to "Car Wash"
- 5 to "VAT - Car Wash"

### Step 4: IFRS Entries Created (At Payment Time)
```
Transaction: Payment Receipt #XXX
Date: [Payment Date]

Debit:
  Cash (1100)                           105.00

Credit:
  Additional Fees (2XXX - Liability)    100.00
  VAT Collection (2XXX - Liability)       5.00
```

### Step 5: Later at Invoice/Contract Closure
```
Transaction: Invoice Recognition
Date: [Invoice Date]

Debit:
  Additional Fees (Liability)           100.00
Credit:
  Revenue (4XXX)                        100.00

Debit:
  VAT Collection (Liability)              5.00
Credit:
  VAT Payable (2200)                      5.00
```

## Testing Results

✅ Query correctly aggregates subtotal and VAT separately
✅ VAT account ID (11 - VAT Payable 2200) correctly retrieved
✅ Two separate rows created per fee type
✅ Localized descriptions display correctly (EN/AR)
✅ Paid amounts track separately for subtotal and VAT
✅ GL account mapping works correctly
✅ IFRS compliance achieved

## Benefits

1. **IFRS Compliant** - Proper liability recognition until invoice is issued
2. **Better Visibility** - Clear breakdown of charges vs. VAT collection
3. **Accurate Tracking** - Individual payment tracking for each component
4. **Audit Trail** - Separate line items in accounting system
5. **Tax Reporting** - Simplified VAT reporting with proper GL accounts
6. **Deferred Revenue** - Revenue recognized only when earned (at invoice time)
7. **Clean GL Structure** - Only 2 accounts used, no account proliferation

## Configuration Required

### Branch Setup
Ensure branches have:
1. **Quick Pay Account Mapping** - `quick_pay_accounts['liability']['additional_fees']` mapped to deferred revenue/liability account
2. **Quick Pay Account Mapping** - `quick_pay_accounts['liability']['vat_collection']` mapped to VAT Collection liability account (تحصيل الضريبة القيمة المضافة)

**Note**: The `ifrs_vat_account_id` on branches is NOT used for Quick Pay. All VAT from additional fees goes to the VAT Collection account configured in Quick Pay mappings.

### Fee Types
System supports 15 fee types, each can have VAT:
- Abu Dhabi Toll Gates (بوابات ابوظبي)
- Car Wash (غسيل سيارات)
- Collection (تحصيل)
- Damages (اضرار)
- Delivery (توصيل)
- Insurance Deductible (بدل تأمين)
- Kilometers (كيلومترات)
- Extra Hours (ساعات اضافية)
- Fuel (بترول)
- Rental Fees (رسوم ايجار)
- Salik Fees (رسوم سالك)
- Salik Parking (مواقف سالك)
- Violations (مخالفات)
- Black Points (نقاط سوداء)
- Vehicle Reservation (حجز مركبة)

## Notes

- VAT-exempt fees only create a subtotal row (no VAT row)
- Multiple fees of the same type are aggregated together
- Each fee type maintains separate subtotal and VAT totals
- Paid amounts are calculated from existing payment receipt allocations
- Frontend (QuickPayModal.vue) requires no changes - automatically displays new row structure
- **Key Point**: "VAT - Car Wash" is just a display description for clarity - ALL VAT amounts go to ONE account (VAT Collection)
- Revenue and VAT Payable are recognized later at invoice time (e.g., contract closure)
- This follows proper accrual accounting - collect cash first, recognize revenue when earned

