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
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uid')->unique(); // Unique identifier for reservation
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 10, 2); // Daily rate
            $table->datetime('pickup_date');
            $table->string('pickup_location');
            $table->datetime('return_date');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'canceled'])->default('pending');
            $table->datetime('reservation_date');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->integer('duration_days')->nullable();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'pickup_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['vehicle_id', 'pickup_date']);
            $table->index('pickup_date');
            $table->index('return_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
