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
        Schema::table('contracts', function (Blueprint $table) {
            // Pickup condition fields (recorded when contract starts)
            $table->integer('pickup_mileage')->nullable()->after('notes')->comment('Vehicle mileage at pickup');
            $table->string('pickup_fuel_level')->nullable()->after('pickup_mileage')->comment('Fuel level at pickup (full, 3/4, 1/2, 1/4, low, empty)');
            $table->json('pickup_condition_photos')->nullable()->after('pickup_fuel_level')->comment('Photos of vehicle condition at pickup');
            
            // Return condition fields (recorded when contract is completed/returned)
            $table->integer('return_mileage')->nullable()->after('pickup_condition_photos')->comment('Vehicle mileage at return');
            $table->string('return_fuel_level')->nullable()->after('return_mileage')->comment('Fuel level at return (full, 3/4, 1/2, 1/4, low, empty)');
            $table->json('return_condition_photos')->nullable()->after('return_fuel_level')->comment('Photos of vehicle condition at return');
            
            // Additional charges calculated from condition comparison
            $table->decimal('excess_mileage_charge', 10, 2)->nullable()->after('return_condition_photos')->comment('Charge for excess mileage');
            $table->decimal('fuel_charge', 10, 2)->nullable()->after('excess_mileage_charge')->comment('Charge for fuel difference');
            
            // Index for performance
            $table->index(['pickup_mileage', 'return_mileage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['pickup_mileage', 'return_mileage']);
            $table->dropColumn([
                'pickup_mileage',
                'pickup_fuel_level', 
                'pickup_condition_photos',
                'return_mileage',
                'return_fuel_level',
                'return_condition_photos',
                'excess_mileage_charge',
                'fuel_charge',
            ]);
        });
    }
};
