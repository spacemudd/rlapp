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
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vehicle_make_id');
            $table->string('name_en');
            $table->string('name_ar');
            $table->uuid('team_id')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_make_id')->references('id')->on('vehicle_makes')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            $table->unique(['vehicle_make_id', 'name_en']);
            $table->index(['vehicle_make_id']);
            $table->index(['name_en']);
            $table->index(['team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
