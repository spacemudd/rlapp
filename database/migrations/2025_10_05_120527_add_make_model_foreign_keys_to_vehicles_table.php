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
            $table->uuid('vehicle_make_id')->nullable()->after('model');
            $table->uuid('vehicle_model_id')->nullable()->after('vehicle_make_id');

            $table->foreign('vehicle_make_id')->references('id')->on('vehicle_makes')->onDelete('set null');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('set null');
            $table->index(['vehicle_make_id']);
            $table->index(['vehicle_model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['vehicle_make_id']);
            $table->dropForeign(['vehicle_model_id']);
            $table->dropIndex(['vehicle_make_id']);
            $table->dropIndex(['vehicle_model_id']);
            $table->dropColumn(['vehicle_make_id', 'vehicle_model_id']);
        });
    }
};
