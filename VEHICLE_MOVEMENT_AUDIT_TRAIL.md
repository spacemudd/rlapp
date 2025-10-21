# Vehicle Movement Audit Trail Implementation

## Overview

This document describes the vehicle movement audit trail system that tracks mileage and condition changes across all vehicle-related events (contracts, maintenance, relocations, inspections).

## Implementation Date
October 20, 2025

## Features Implemented

### 1. Database Structure

**Table:** `vehicle_movements`

A new table that stores comprehensive audit trail records for all vehicle movements and mileage recordings.

**Fields:**
- `id` - UUID primary key
- `vehicle_id` - Foreign key to vehicles
- `event_type` - Type of movement (contract_pickup, contract_return, maintenance, inspection, relocation, manual_adjustment, other)
- `mileage` - Recorded mileage (required)
- `fuel_level` - Fuel level at the time (optional)
- `location_id` - Foreign key to locations (optional)
- `contract_id` - Foreign key to contracts (optional)
- `photos` - JSON array of photo paths (optional)
- `notes` - Additional notes (optional)
- `performed_by_user_id` - Foreign key to users (required)
- `performed_at` - Timestamp of the movement
- `metadata` - JSON object for additional contextual data
- Timestamps (created_at, updated_at)

**Indexes:**
- `vehicle_id`
- `event_type`
- `performed_at`
- `contract_id`
- Composite index on `[vehicle_id, performed_at]`

### 2. Backend Components

#### VehicleMovement Model
`app/Models/VehicleMovement.php`

- Full Eloquent model with relationships
- Relationships: `vehicle()`, `location()`, `contract()`, `performedBy()`
- Scopes: `forVehicle()`, `byEventType()`, `recent()`
- Static method: `getLatestMileageForVehicle()` - retrieves the most recent mileage for a vehicle
- Dynamic attribute: `event_type_label` - returns translated label for event type

#### VehicleMovementService
`app/Services/VehicleMovementService.php`

Centralized service for recording all vehicle movements with automatic mileage validation.

**Key Methods:**
- `recordMovement()` - Generic method to record any type of vehicle movement
- `getLastRecordedMileage()` - Get the most recent mileage for a vehicle
- `getMovementHistory()` - Get movement history with optional limit
- `recordContractPickup()` - Automatically record when contract starts
- `recordContractReturn()` - Automatically record when contract ends
- `recordMaintenance()` - Record maintenance events
- `recordInspection()` - Record inspection events
- `recordRelocation()` - Record vehicle relocations
- `recordManualAdjustment()` - Record manual mileage adjustments

**Built-in Validations:**
- Validates that new mileage >= previous mileage (prevents odometer rollback)
- Exception for `manual_adjustment` event type to allow corrections
- Automatically updates vehicle's `current_mileage` field

#### VehicleMovementController
`app/Http/Controllers/VehicleMovementController.php`

- `index()` - Display movement history for a vehicle (Inertia page)
- `store()` - Create manual movement records
- `getHistory()` - API endpoint to retrieve movement history

#### Updated Controllers

**ContractController** (`app/Http/Controllers/ContractController.php`):
- `store()` method - Records vehicle pickup movement when contract is created
- `finalize()` method - Records vehicle return movement when contract is finalized
- `close()` method - Records vehicle return movement when contract is closed

**VehicleController** (`app/Http/Controllers/VehicleController.php`):
- `getLastMileage()` - New API endpoint to get the last recorded mileage for a vehicle

#### Updated Models

**Vehicle Model** (`app/Models/Vehicle.php`):
- Added `movements()` relationship - HasMany relationship to all movements
- Added `latestMovement()` relationship - HasOne relationship to the most recent movement

### 3. API Endpoints

**Web Routes** (`routes/web.php`):
```php
Route::get('/vehicles/{vehicle}/movements', [VehicleMovementController::class, 'index']);
Route::post('/vehicles/{vehicle}/movements', [VehicleMovementController::class, 'store']);
```

**API Routes** (`routes/api.php`):
```php
Route::get('/vehicles/{vehicle}/last-mileage', [VehicleController::class, 'getLastMileage']);
Route::get('/vehicles/{vehicle}/movements', [VehicleMovementController::class, 'getHistory']);
```

### 4. Frontend Integration

#### Contract Create Page
`resources/js/pages/Contracts/Create.vue`

**New Features:**
- Added `lastRecordedMileage` ref to store last mileage information
- Added `fetchLastMileage()` function to retrieve last mileage from API
- Integrated into `handleVehicleSelected()` to auto-fetch when vehicle is selected
- Auto-populates `current_mileage` field with last recorded mileage
- Displays last recorded mileage information below the input field with:
  - Mileage value (in LTR direction for numbers)
  - Recorded date
  - Event type (optional)

**User Experience:**
1. User selects a vehicle
2. System automatically fetches the last recorded mileage
3. The mileage field is pre-filled if empty
4. User sees helpful hint: "Last Recorded Mileage: 15000 km - Recorded on 10/15/2025"
5. User can still manually override the value if needed

### 5. Translations

All translations added to:
- `lang/en/words.php`
- `lang/ar/words.php`
- `resources/js/lib/i18n.ts`

**New Translation Keys:**
- `vehicle_movement` - Vehicle Movement / حركة المركبة
- `movement_history` - Movement History / سجل الحركات
- `event_type` - Event Type / نوع الحدث
- `contract_pickup` - Contract Pickup / استلام العقد
- `contract_return` - Contract Return / إرجاع العقد
- `maintenance` - Maintenance / صيانة
- `inspection` - Inspection / فحص
- `relocation` - Relocation / نقل
- `manual_adjustment` - Manual Adjustment / تعديل يدوي
- `other` - Other / أخرى
- `last_recorded_mileage` - Last Recorded Mileage / آخر عداد مسجل
- `performed_by` - Performed By / تم بواسطة
- `performed_at` - Performed At / تم في
- `recorded_on` - Recorded On / مسجل في

## Event Types

The system supports the following event types:

1. **contract_pickup** - Recorded when a vehicle is handed to a customer (contract starts)
2. **contract_return** - Recorded when a vehicle is returned by a customer (contract ends)
3. **maintenance** - Recorded during maintenance activities
4. **inspection** - Recorded during vehicle inspections
5. **relocation** - Recorded when a vehicle is moved to a different location
6. **manual_adjustment** - Recorded for manual mileage corrections (bypasses validation)
7. **other** - Recorded for any other type of event

## Automatic Recording

The system automatically records vehicle movements in the following scenarios:

### Contract Creation
- **Trigger:** When a new contract is created and saved
- **Event Type:** `contract_pickup`
- **Data Recorded:**
  - Mileage from `contract.pickup_mileage`
  - Fuel level from `contract.pickup_fuel_level`
  - Photos from `contract.pickup_condition_photos`
  - Location from `vehicle.location_id`
  - Contract ID
  - Metadata: contract_number, customer_name, dates

### Contract Finalization/Completion
- **Trigger:** When a contract is finalized or closed
- **Event Type:** `contract_return`
- **Data Recorded:**
  - Mileage from `contract.return_mileage`
  - Fuel level from `contract.return_fuel_level`
  - Photos from `contract.return_condition_photos`
  - Location from `vehicle.location_id`
  - Contract ID
  - Metadata: contract_number, customer_name, mileage_driven, charges

## Manual Recording

Users can manually record vehicle movements through:

1. **Web Interface** - `POST /vehicles/{vehicle}/movements`
   - For recording maintenance, inspections, relocations, or manual adjustments
   - Supports photo uploads
   - Supports custom notes and metadata

2. **API Endpoint** - Same endpoint available via API
   - Useful for integrations or external systems

## Data Validation

### Mileage Validation
- New mileage must be >= previous recorded mileage
- Exception: `manual_adjustment` event type bypasses this validation
- Throws `InvalidArgumentException` if validation fails

### Vehicle Update
- Automatically updates `vehicle.current_mileage` after every successful recording
- Ensures the vehicle table always reflects the latest mileage

## Querying Movement History

### Get Latest Mileage
```php
$lastMileage = VehicleMovement::getLatestMileageForVehicle($vehicleId);
```

### Get Movement History
```php
$service = new VehicleMovementService();
$history = $service->getMovementHistory($vehicle, $limit = 50);
```

### Get Via Relationship
```php
$vehicle = Vehicle::with('latestMovement')->find($id);
$lastMileage = $vehicle->latestMovement?->mileage;

$vehicle = Vehicle::with('movements')->find($id);
$allMovements = $vehicle->movements; // Already ordered by performed_at desc
```

## Benefits

1. **Complete Audit Trail** - Every mileage change is tracked with who, when, and why
2. **Automatic Recording** - Contract pickups and returns are automatically logged
3. **Data Integrity** - Validation prevents odometer rollback (except for corrections)
4. **Historical Context** - All movements include metadata, photos, and notes
5. **Easy Access** - Simple API to get last mileage for any vehicle
6. **User-Friendly** - Auto-populates mileage fields in contract creation
7. **Flexible** - Supports various event types for different scenarios
8. **Localized** - Full support for English and Arabic languages

## Future Enhancements

Possible future improvements:
- Vehicle Movement History UI page (frontend component already planned)
- Reports and analytics on vehicle usage
- Alerts for unusual mileage changes
- Integration with maintenance scheduling based on mileage
- Export movement history to PDF/Excel
- Vehicle movement timeline visualization

## Migration

Run the migration to create the table:
```bash
php artisan migrate
```

The migration file is: `database/migrations/2025_10_20_200000_create_vehicle_movements_table.php`

## Testing

To test the implementation:

1. **Contract Creation:**
   - Create a new contract
   - Fill in the pickup mileage
   - Verify a movement record is created in `vehicle_movements`
   - Verify `vehicle.current_mileage` is updated

2. **Contract Completion:**
   - Finalize or close an active contract
   - Verify a return movement is recorded
   - Verify `vehicle.current_mileage` is updated to return mileage

3. **Auto-populate:**
   - Create a new contract
   - Select a vehicle that has previous movement records
   - Verify the mileage field is auto-populated
   - Verify the hint text shows the last recorded mileage

4. **Manual Recording:**
   - Use the API or web interface to record a manual movement
   - Verify validation works (cannot decrease mileage)
   - Verify `manual_adjustment` bypasses validation

## Notes

- All movement records are immutable (no update/delete functionality implemented)
- The system uses soft validation with try-catch blocks to prevent contract creation failures
- Photos are stored in the `public/vehicle_movements` directory
- The system respects RTL/LTR directionality for Arabic/English interfaces
- Numbers (mileage) are always displayed LTR regardless of interface language

