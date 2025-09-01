<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add essential missing columns for customer creation
        if (Schema::hasTable('customers')) {
            $table = 'customers';

            // Add visit_visa_pdf_path column if it doesn't exist
            if (!Schema::hasColumn($table, 'visit_visa_pdf_path')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('visit_visa_pdf_path')->nullable()->after('trade_license_pdf_path');
                });
            }

            // Update secondary_identification_type enum to include 'visit_visa' if needed
            if (Schema::hasColumn($table, 'secondary_identification_type')) {
                try {
                    DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('passport', 'resident_id', 'visit_visa') NULL");
                } catch (\Exception $e) {
                    // If the enum already has the correct values, ignore the error
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers')) {
            $table = 'customers';

            // Remove visit_visa_pdf_path column
            if (Schema::hasColumn($table, 'visit_visa_pdf_path')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('visit_visa_pdf_path');
                });
            }

            // Revert secondary_identification_type enum (remove visit_visa)
            if (Schema::hasColumn($table, 'secondary_identification_type')) {
                try {
                    DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('passport', 'resident_id') NULL");
                } catch (\Exception $e) {
                    // Ignore errors during rollback
                }
            }
        }
    }
};
