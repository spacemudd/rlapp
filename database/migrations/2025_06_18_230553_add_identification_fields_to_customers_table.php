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
            // Add identification type field
            $table->enum('identification_type', ['drivers_license', 'passport', 'resident_id'])->nullable()->after('phone');
            
            // Add passport fields
            $table->string('passport_number')->nullable()->after('drivers_license_expiry');
            $table->date('passport_expiry')->nullable()->after('passport_number');
            
            // Add resident ID fields
            $table->string('resident_id_number')->nullable()->after('passport_expiry');
            $table->date('resident_id_expiry')->nullable()->after('resident_id_number');
            
            // Add nationality field
            $table->string('nationality')->nullable()->after('country');
            
            // Make drivers_license fields nullable since they're not always required now
            $table->string('drivers_license_number')->nullable()->change();
            $table->date('drivers_license_expiry')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'identification_type',
                'passport_number',
                'passport_expiry',
                'resident_id_number',
                'resident_id_expiry',
                'nationality'
            ]);
            
            // Revert drivers_license fields to required
            $table->string('drivers_license_number')->nullable(false)->change();
            $table->date('drivers_license_expiry')->nullable(false)->change();
        });
    }
};
