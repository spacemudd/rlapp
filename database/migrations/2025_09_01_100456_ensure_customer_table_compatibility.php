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
        // Ensure all required columns exist in customers table
        if (Schema::hasTable('customers')) {
            $table = 'customers';

            // Add missing columns if they don't exist
            if (!Schema::hasColumn($table, 'visit_visa_pdf_path')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('visit_visa_pdf_path')->nullable()->after('trade_license_pdf_path');
                });
            }

            // Ensure city column is nullable
            if (Schema::hasColumn($table, 'city')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('city')->nullable()->change();
                });
            }

            // Update secondary_identification_type enum to include 'visit_visa'
            if (Schema::hasColumn($table, 'secondary_identification_type')) {
                DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('emirates_id', 'passport', 'visit_visa') NULL");
            }

            // Ensure address column is removed (if it exists)
            if (Schema::hasColumn($table, 'address')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('address');
                });
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

            // Revert city column to not nullable
            if (Schema::hasColumn($table, 'city')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('city')->nullable(false)->change();
                });
            }

            // Revert secondary_identification_type enum
            if (Schema::hasColumn($table, 'secondary_identification_type')) {
                DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('emirates_id', 'passport') NULL");
            }

            // Re-add address column
            if (!Schema::hasColumn($table, 'address')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->text('address')->nullable();
                });
            }
        }
    }
};
