<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add the new secondary identification type field
            $table->enum('secondary_identification_type', ['passport', 'resident_id'])->nullable()->after('phone');
            
            // Make drivers_license fields required again (they're always required now)
            $table->string('drivers_license_number')->nullable(false)->change();
            $table->date('drivers_license_expiry')->nullable(false)->change();
            
            // Remove the old identification_type field
            $table->dropColumn('identification_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add back the old identification_type field
            $table->enum('identification_type', ['drivers_license', 'passport', 'resident_id'])->nullable()->after('phone');
            
            // Make drivers_license fields nullable again
            $table->string('drivers_license_number')->nullable()->change();
            $table->date('drivers_license_expiry')->nullable()->change();
            
            // Remove the new secondary identification type field
            $table->dropColumn('secondary_identification_type');
        });
    }
};
