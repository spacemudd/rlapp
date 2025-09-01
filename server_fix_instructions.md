# Server Fix Instructions - Customer Creation 500 Error

## Problem
Customer creation is failing with 500 error due to missing database columns and enum mismatches.

## Solution

### 1. Delete Problematic Migration Files (if they exist)
```bash
rm database/migrations/2025_08_27_124636_make_address_nullable_in_customers_table.php
rm database/migrations/2025_08_27_135424_remove_address_column_from_customers_table.php
```

### 2. Run New Safe Migration
```bash
php artisan migrate
```

This will run the new migration `2025_09_01_103006_fix_customer_migrations_for_server.php` which:
- Adds `visit_visa_pdf_path` column if missing
- Updates ENUM to include 'visit_visa'
- Does NOT touch the address column (which doesn't exist on server)

### 3. Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### 4. Rebuild Frontend
```bash
npm run build
```

### 5. Test Customer Creation
Try creating a customer at: https://rlapp.rentluxuria.com/customers/create

## Manual SQL Fix (if migration fails)

If the migration still fails, run these SQL commands manually:

```sql
-- Add visit_visa_pdf_path column
ALTER TABLE customers ADD COLUMN visit_visa_pdf_path VARCHAR(255) NULL AFTER trade_license_pdf_path;

-- Update ENUM to include visit_visa
ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('emirates_id', 'passport', 'visit_visa') NULL;
```

## Key Changes Made

1. **Fixed validation rules** in StoreCustomerRequest.php and UpdateCustomerRequest.php
2. **Removed problematic migrations** that tried to modify non-existent address column
3. **Created safe migration** that only adds essential missing columns
4. **Updated ENUM values** to match application requirements

The main issue was migrations trying to modify a column that never existed on the server.
