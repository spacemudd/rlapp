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
        Schema::create('vehicle_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vehicle_id');
            $table->enum('event_type', [
                'contract_pickup',
                'contract_return',
                'maintenance',
                'inspection',
                'relocation',
                'manual_adjustment',
                'other'
            ]);
            $table->integer('mileage');
            $table->string('fuel_level')->nullable()->comment('full, 3/4, 1/2, 1/4, low, empty');
            $table->uuid('location_id')->nullable();
            $table->uuid('contract_id')->nullable();
            $table->json('photos')->nullable()->comment('Array of photo paths');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by_user_id');
            $table->timestamp('performed_at');
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->timestamps();

            // Foreign keys
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('set null');
            $table->foreign('performed_by_user_id')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('vehicle_id');
            $table->index('event_type');
            $table->index('performed_at');
            $table->index('contract_id');
            $table->index(['vehicle_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_movements');
    }
};

