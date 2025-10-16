# Additional Fees Feature - Implementation Summary

## Overview
Implemented a comprehensive Additional Fees system that allows users to add custom charges (damages, cleaning fees, late return fees, etc.) to contracts. These fees are stored in a dedicated table, managed via system-wide settings, and integrated with the Quick Pay workflow following IFRS accounting standards.

## Implementation Date
October 15, 2025

## Database Changes

### 1. System Settings Table
**Migration**: `2025_10_15_000001_create_system_settings_table.php`
- Created `system_settings` table for storing system-wide configurations
- Seeded with default fee types:
  - Damages (أضرار)
  - Cleaning Fee (رسوم التنظيف)
  - Late Return Fee (رسوم التأخير)

### 2. Contract Additional Fees Table
**Migration**: `2025_10_15_000002_create_contract_additional_fees_table.php`
- Created `contract_additional_fees` table with columns:
  - `id` (UUID)
  - `contract_id` (foreign key to contracts)
  - `fee_type` (string - references system settings)
  - `description` (text)
  - `quantity` (decimal)
  - `unit_price` (decimal)
  - `discount` (decimal)
  - `subtotal` (decimal - auto-calculated)
  - `vat_account_id` (foreign key to ifrs_accounts)
  - `vat_amount` (decimal - auto-calculated)
  - `is_vat_exempt` (boolean)
  - `total` (decimal - auto-calculated)
  - `created_by` (foreign key to users)
  - Timestamps
- Added indexes on `contract_id`, `fee_type`, and combined index for performance

## Backend Implementation

### Models Created
1. **SystemSetting** (`app/Models/SystemSetting.php`)
   - Manages system-wide settings
   - Helper methods: `get()`, `set()`, `getFeeTypes()`, `setFeeTypes()`
   - Casts `value` as array for JSON storage

2. **ContractAdditionalFee** (`app/Models/ContractAdditionalFee.php`)
   - Manages additional fees for contracts
   - Auto-calculates subtotal, VAT amount, and total using model observers
   - Relationships: `contract()`, `vatAccount()`, `creator()`

### Controllers Created/Updated
1. **SystemSettingsController** (`app/Http/Controllers/Settings/SystemSettingsController.php`)
   - `index()`: Load fee types management page
   - `updateFeeTypes()`: Save fee types configuration

2. **ContractAdditionalFeesController** (`app/Http/Controllers/ContractAdditionalFeesController.php`)
   - `store()`: Create multiple fee items for a contract
   - `destroy()`: Delete a fee item
   - Validates fee types against system settings

3. **ContractController** (updated `quickPaySummary()` method)
   - Queries and aggregates additional fees by type
   - Adds fees to liability section of Quick Pay with:
     - Row ID format: `additional_fee_{fee_type}`
     - Localized description based on app locale
     - Total, paid, and remaining amounts
     - GL account from branch configuration

4. **BranchController** (updated `edit()` method)
   - Added `additional_fees` to quick pay lines configuration
   - Allows branches to specify GL account for additional fees

## Frontend Implementation

### Components Created
1. **AdditionalFeesModal** (`resources/js/components/AdditionalFeesModal.vue`)
   - Modal for adding fees to contracts
   - Features:
     - Multi-row input for multiple fee items
     - Fee type dropdown (from system settings)
     - Quantity, unit price, and discount inputs
     - VAT exemption toggle
     - Automatic VAT calculation (5%)
     - Real-time subtotal and total calculations
     - Add/remove item functionality
     - Grand totals display

2. **FeeTypes Management Page** (`resources/js/pages/settings/FeeTypes.vue`)
   - Interface for managing system-wide fee types
   - Add/remove fee types
   - English and Arabic names for each type
   - Validation ensuring both languages are provided

### Pages Updated
1. **Contracts/Show.vue**
   - Integrated AdditionalFeesModal
   - Added "Additional Fees" button
   - Handles fee submission and page refresh

2. **settings/SystemSettings.vue**
   - Added link to Fee Types management page
   - Icon and card for navigation

3. **Branches/Show.vue**
   - Displays additional_fees GL account in Quick Pay settings

4. **Branches/Edit.vue**
   - Automatically includes additional_fees field (dynamic based on quickPayLines)

## Translations Added

### English (`lang/en/words.php` & `resources/js/lib/i18n.ts`)
- additional_fees
- fee_types
- fee_type
- manage_fee_types
- add_fee_type
- english_name
- arabic_name
- quantity
- unit_price
- discount
- subtotal
- vat_exempt
- vat_account
- grand_total
- add_item
- damages
- cleaning_fee
- late_return_fee
- select_fee_type
- enter_description
- fee_added_successfully
- fees_added_successfully

### Arabic (`lang/ar/words.php` & `resources/js/lib/i18n.ts`)
- All corresponding Arabic translations

## Routes Added

### Web Routes (`routes/web.php`)
```php
Route::post('/contracts/{contract}/additional-fees', [ContractAdditionalFeesController::class, 'store'])
Route::delete('/additional-fees/{fee}', [ContractAdditionalFeesController::class, 'destroy'])
```

### Settings Routes (`routes/settings.php`)
```php
Route::get('/settings/fee-types', [SystemSettingsController::class, 'index'])
Route::post('/settings/fee-types', [SystemSettingsController::class, 'updateFeeTypes'])
```

### API Routes (`routes/api.php`)
```php
Route::get('/api/system-settings/fee-types') // Get fee types
Route::get('/api/branches/{branch}/vat-account') // Get branch VAT account
```

## IFRS Accounting Integration

### Classification
- Additional fees are recorded as **customer liabilities** (amounts they owe us)
- Appear in the **Liability section** of Quick Pay modal
- Each fee type is aggregated and shown as a single line item

### Quick Pay Integration
- Fees are aggregated by fee type
- Row ID format: `additional_fee_{fee_type}`
- Localized descriptions based on app locale
- Totals include: amount charged, amount paid, amount remaining
- GL account mapping from branch configuration

### Calculations
```
Subtotal = (Quantity × Unit Price) - Discount
VAT Amount = Subtotal × 0.05 (if not exempt)
Total = Subtotal + VAT Amount
```

## User Workflow

### Adding Additional Fees
1. Navigate to a contract's show page
2. Click "Additional Fees" button
3. Modal opens with fee entry form
4. For each fee:
   - Select fee type from dropdown
   - Enter description (optional)
   - Enter quantity, unit price, and discount
   - Choose VAT exemption or use branch VAT account
   - View real-time calculations
5. Add more items as needed
6. Review grand totals
7. Submit fees
8. Fees are saved and page refreshes

### Managing Fee Types
1. Navigate to System Settings
2. Click "Manage Fee Types"
3. Add/edit/remove fee types
4. Each type requires English and Arabic names
5. Save changes

### Quick Pay Integration
1. Open Quick Pay modal from contract page
2. Additional fees appear in Liability section
3. Fees are aggregated by type
4. Allocate payments to fee balances
5. Submit payment allocation

### Branch Configuration
1. Navigate to Branches → Edit
2. Quick Pay Settings section
3. Liability subsection includes "Additional Fees" field
4. Select GL account for additional fees posting
5. Save branch configuration

## Key Features

✅ System-wide fee type management
✅ Multi-language support (English & Arabic)
✅ Flexible quantity-based pricing
✅ Discount support
✅ VAT calculation and exemption options
✅ Real-time calculations
✅ Multi-item entry in single modal
✅ Integration with Quick Pay workflow
✅ IFRS-compliant accounting
✅ Branch-level GL account configuration
✅ Aggregation by fee type in Quick Pay
✅ Payment tracking and allocation

## Testing Recommendations

1. **Unit Tests**
   - SystemSetting model CRUD operations
   - ContractAdditionalFee calculations
   - Fee type validation

2. **Feature Tests**
   - Add fees to contract via API
   - Delete fee items
   - Fee types management
   - Quick Pay summary includes fees

3. **Integration Tests**
   - Full workflow: add fees → Quick Pay → allocate payment
   - VAT calculations
   - Branch GL account mapping

## Future Enhancements

- Fee templates for common scenarios
- Bulk fee application across multiple contracts
- Fee approval workflow
- Fee history and audit log
- Export fees to Excel/PDF
- Fee analytics and reporting

## Files Modified/Created

### Created
- `database/migrations/2025_10_15_000001_create_system_settings_table.php`
- `database/migrations/2025_10_15_000002_create_contract_additional_fees_table.php`
- `app/Models/SystemSetting.php`
- `app/Models/ContractAdditionalFee.php`
- `app/Http/Controllers/Settings/SystemSettingsController.php`
- `app/Http/Controllers/ContractAdditionalFeesController.php`
- `resources/js/components/AdditionalFeesModal.vue`
- `resources/js/pages/settings/FeeTypes.vue`

### Modified
- `app/Models/Contract.php` (added relationship)
- `app/Http/Controllers/ContractController.php` (quickPaySummary method)
- `app/Http/Controllers/BranchController.php` (edit method)
- `resources/js/pages/Contracts/Show.vue` (integrated modal)
- `resources/js/pages/settings/SystemSettings.vue` (added navigation)
- `resources/js/pages/Branches/Show.vue` (display GL account)
- `routes/web.php` (additional fees routes)
- `routes/settings.php` (fee types routes)
- `routes/api.php` (API endpoints)
- `lang/en/words.php` (translations)
- `lang/ar/words.php` (translations)
- `resources/js/lib/i18n.ts` (frontend translations)

## Notes

- All calculations are performed in the model layer using observers
- VAT rate is fixed at 5% (can be made configurable if needed)
- Fee types are system-wide but GL accounts are configured per branch
- Fees can only be added to existing contracts
- Deleted fees cannot be recovered (consider soft deletes if needed)
- The feature follows Laravel and IFRS best practices

