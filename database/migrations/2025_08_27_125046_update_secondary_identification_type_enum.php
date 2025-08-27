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
        // Update the ENUM to include 'visit_visa'
        DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('passport', 'resident_id', 'visit_visa')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original ENUM values
        DB::statement("ALTER TABLE customers MODIFY COLUMN secondary_identification_type ENUM('passport', 'resident_id')");
    }
};
