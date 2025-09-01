# Server Deployment Instructions

## Fix Customer Creation 500 Error

The customer creation is failing on the server due to database schema inconsistencies. Follow these steps to fix the issue:

### 1. Run the New Migration

```bash
php artisan migrate
```

This will run the new migrations which:
- Add missing `visit_visa_pdf_path` column if it doesn't exist
- Update `secondary_identification_type` ENUM to include 'visit_visa'
- Handle any missing columns safely without touching the address column

### 2. Clear All Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### 3. Rebuild Frontend Assets

```bash
npm run build
```

### 4. Check Database Schema

Verify that the customers table has the correct structure:

```sql
DESCRIBE customers;
```

Make sure these columns exist:
- `visit_visa_pdf_path` (VARCHAR, nullable)
- `city` (VARCHAR, nullable)
- `secondary_identification_type` (ENUM with 'emirates_id', 'passport', 'visit_visa')

### 5. Test Customer Creation

Try creating a customer at: https://rlapp.rentluxuria.com/customers/create

### Troubleshooting

If you still get 500 errors:

1. Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

2. Check server error logs:
```bash
tail -f /var/log/nginx/error.log
# or
tail -f /var/log/apache2/error.log
```

3. Ensure file permissions are correct:
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

4. If the migration fails, you can run it manually:
```sql
-- Add visit_visa_pdf_path column
ALTER TABLE customers ADD COLUMN visit_visa_pdf_path VARCHAR(255) NULL AFTER trade_license_pdf_path;

-- Make city nullable
ALTER TABLE customers MODIFY COLUMN city VARCHAR(255) NULL;

-- Update ENUM
ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('emirates_id', 'passport', 'visit_visa') NULL;

-- Note: address column was never created on server
```

### Key Changes Made

1. **Fixed validation rules** in `StoreCustomerRequest.php` and `UpdateCustomerRequest.php`:
   - Changed `'passport,resident_id,visit_visa'` to `'emirates_id,passport,visit_visa'`
   - Updated switch statements to handle `emirates_id` instead of `resident_id`

2. **Added compatibility migration** to ensure database schema matches the application requirements

3. **Ensured all required columns** are present in the customers table

The main issue was a mismatch between the database ENUM values and the validation rules in the application.
