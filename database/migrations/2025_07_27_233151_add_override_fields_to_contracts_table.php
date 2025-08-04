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
            // Override fields for pricing
            $table->boolean('override_daily_rate')->default(false);
            $table->boolean('override_final_price')->default(false);
            $table->decimal('original_calculated_amount', 10, 2)->nullable();
            $table->text('override_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'override_daily_rate',
                'override_final_price',
                'original_calculated_amount',
                'override_reason'
            ]);
        });
    }
};
