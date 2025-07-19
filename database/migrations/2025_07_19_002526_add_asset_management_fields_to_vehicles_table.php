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
        Schema::table('vehicles', function (Blueprint $table) {
            // Asset Management & Depreciation Fields (only adding new ones)
            $table->date('last_depreciation_date')->nullable()->comment('Date of last depreciation entry');
            $table->decimal('estimated_recoverable_amount', 15, 2)->nullable()->comment('Estimated recoverable amount for impairment testing');
            
            // Asset Disposal Fields
            $table->boolean('is_active')->default(true)->comment('Whether the asset is still active');
            $table->date('disposal_date')->nullable()->comment('Date when the asset was disposed');
            $table->string('disposal_method')->nullable()->comment('Method of disposal: sale, trade_in, scrapped, donated, lost');
            $table->decimal('sale_price', 15, 2)->nullable()->comment('Price received from disposal');
            $table->decimal('disposal_gain_loss', 15, 2)->nullable()->comment('Gain or loss on disposal');
            $table->text('disposal_notes')->nullable()->comment('Additional notes about disposal');
            
            // Additional Asset Tracking
            $table->decimal('current_mileage', 10, 2)->nullable()->comment('Current mileage reading');
            $table->decimal('total_expected_mileage', 10, 2)->nullable()->comment('Expected total mileage over useful life');
            $table->string('asset_tag')->nullable()->comment('Physical asset tag number');
            $table->string('insurance_policy_number')->nullable()->comment('Insurance policy number');
            $table->date('insurance_expiry')->nullable()->comment('Insurance expiry date');
            $table->date('last_maintenance_date')->nullable()->comment('Date of last maintenance');
            $table->date('next_maintenance_due')->nullable()->comment('Date when next maintenance is due');
            $table->decimal('annual_maintenance_cost', 10, 2)->nullable()->comment('Estimated annual maintenance cost');
            
            // Indexing for performance
            $table->index(['is_active', 'depreciation_method']);
            $table->index(['disposal_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex(['is_active', 'depreciation_method']);
            $table->dropIndex(['disposal_date']);
            
            // Remove only the fields we added
            $table->dropColumn([
                'last_depreciation_date',
                'estimated_recoverable_amount',
                'is_active',
                'disposal_date',
                'disposal_method',
                'sale_price',
                'disposal_gain_loss',
                'disposal_notes',
                'current_mileage',
                'total_expected_mileage',
                'asset_tag',
                'insurance_policy_number',
                'insurance_expiry',
                'last_maintenance_date',
                'next_maintenance_due',
                'annual_maintenance_cost',
            ]);
        });
    }
};
