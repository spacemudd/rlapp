# Contract Pricing Override Feature Implementation

## Overview
This document outlines the implementation of the pricing override feature for the car rental SaaS application. The feature allows users to override either the daily rate or the final price when creating contracts, providing flexibility for special pricing scenarios.

## Features Implemented

### 1. Database Schema Changes
- **Migration**: `2025_07_27_233151_add_override_fields_to_contracts_table.php`
- **New Fields**:
  - `override_daily_rate` (boolean) - Indicates if daily rate was overridden
  - `override_final_price` (boolean) - Indicates if final price was overridden
  - `original_calculated_amount` (decimal) - Stores the original calculated amount
  - `override_reason` (text) - Optional reason for the override

### 2. Backend Implementation

#### Contract Model (`app/Models/Contract.php`)
- Added override fields to `$fillable` array
- Added proper casting for boolean and decimal fields
- Added helper methods:
  - `hasPricingOverrides()` - Check if any overrides are applied
  - `getOverridePercentage()` - Calculate override percentage
  - `getOverrideDifference()` - Calculate absolute difference
  - `isOverrideDiscount()` - Check if override is a discount
  - `isOverrideMarkup()` - Check if override is a markup

#### Contract Controller (`app/Http/Controllers/ContractController.php`)
- Updated validation rules to include override fields
- Enhanced `store()` method to handle override logic:
  - **Daily Rate Override**: Uses provided daily rate, calculates total amount
  - **Final Price Override**: Uses provided final price, calculates daily rate
  - **No Override**: Uses PricingService for automatic calculation
- Stores original calculated amount for comparison

### 3. Frontend Implementation

#### Contract Creation Form (`resources/js/pages/Contracts/Create.vue`)
- Added Checkbox component import
- Added override fields to form data:
  - `override_daily_rate`
  - `override_final_price`
  - `final_price_override`
  - `override_reason`
- Added reactive tracking variables:
  - `calculatedDailyRate`
  - `originalTotalAmount`
- Implemented override handler functions:
  - `handleRateOverride()` - Manages daily rate override
  - `handleFinalPriceOverride()` - Manages final price override
- Enhanced UI with:
  - Toggle checkboxes for each override type
  - Conditional input fields
  - Original calculated value display
  - Override reason textarea
  - Visual indicators for active overrides

#### Contract Show Page (`resources/js/pages/Contracts/Show.vue`)
- Updated Contract interface to include override fields
- Added override information display in financial details section
- Shows override type, original amount, difference, and reason

## Usage Scenarios

### 1. Daily Rate Override
**Use Case**: Special customer pricing, seasonal rates, or promotional discounts
**Process**:
1. Select vehicle and dates
2. Check "Override calculated rate" checkbox
3. Enter custom daily rate
4. Optionally provide reason
5. Total amount is automatically recalculated

### 2. Final Price Override
**Use Case**: Bulk bookings, package deals, or negotiated rates
**Process**:
1. Select vehicle and dates
2. Check "Override total amount" checkbox
3. Enter final total price
4. Optionally provide reason
5. Daily rate is automatically recalculated

### 3. No Override (Default)
**Use Case**: Standard pricing using vehicle's configured rates
**Process**:
1. Select vehicle and dates
2. System automatically calculates pricing using PricingService
3. Daily rate and total amount are set automatically

## Business Logic

### Override Priority
1. **Final Price Override** (highest priority)
   - If enabled, uses provided final price
   - Calculates daily rate as: `final_price / total_days`
   - Disables daily rate override

2. **Daily Rate Override** (medium priority)
   - If enabled, uses provided daily rate
   - Calculates total amount as: `daily_rate × total_days`
   - Disables final price override

3. **Automatic Calculation** (default)
   - Uses PricingService for sophisticated pricing
   - Applies weekly/monthly discounts based on duration
   - Stores original calculated amount for comparison

### Audit Trail
- All overrides are tracked with timestamps
- Original calculated amounts are preserved
- Override reasons are stored for compliance
- Override percentages and differences are calculated

## UI/UX Features

### Visual Indicators
- ⚠️ Warning icon for active overrides
- Color-coded information (orange for overrides)
- Clear distinction between original and override values
- Responsive design for mobile and desktop

### User Experience
- Intuitive toggle switches
- Real-time calculations
- Clear feedback on changes
- Optional reason field for documentation
- Disabled states to prevent conflicts

### Data Validation
- Required fields validation
- Numeric range validation
- Cross-field validation (mutual exclusivity)
- Server-side validation for security

## Testing

### Test Cases Created
- Daily rate override functionality
- Final price override functionality
- No override (default) behavior
- Override percentage calculations
- Discount vs markup detection

### Manual Testing Steps
1. Create contract with daily rate override
2. Create contract with final price override
3. Create contract without overrides
4. Verify calculations are correct
5. Check override information display

## Security Considerations

### Input Validation
- Server-side validation of all override fields
- Numeric range validation
- XSS protection for reason field
- CSRF protection for all forms

### Authorization
- Override functionality available to authorized users
- Audit trail for compliance
- No sensitive data exposure

## Future Enhancements

### Potential Improvements
1. **Approval Workflow**: Add approval process for large overrides
2. **Override Limits**: Set maximum/minimum override percentages
3. **Bulk Overrides**: Apply overrides to multiple contracts
4. **Override Templates**: Save common override scenarios
5. **Analytics**: Track override patterns and reasons
6. **Notifications**: Alert managers of large overrides

### Integration Points
- Invoice generation with override information
- Reporting with override analytics
- Customer communication with override details
- Accounting system integration

## Technical Notes

### Database Performance
- Indexed fields for efficient queries
- Proper foreign key relationships
- Optimized queries for override calculations

### Code Quality
- Follows Laravel best practices
- Proper separation of concerns
- Comprehensive error handling
- Clean, maintainable code structure

### Compatibility
- Works with existing contract workflow
- Backward compatible with existing data
- No breaking changes to existing functionality

## Conclusion

The pricing override feature provides essential flexibility for real-world car rental operations while maintaining data integrity and audit trails. The implementation follows Laravel best practices and provides a smooth user experience for both simple and complex pricing scenarios. 