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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plate_number')->unique();
            $table->string('make');
            $table->string('model');
            $table->year('year');
            $table->string('color');
            $table->string('category');
            $table->decimal('price_daily', 10, 2)->nullable();
            $table->decimal('price_weekly', 10, 2)->nullable();
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->string('current_location')->nullable();
            $table->enum('status', ['available', 'rented', 'maintenance', 'out_of_service'])->default('available');
            $table->dateTime('expected_return_date')->nullable();
            $table->integer('upcoming_reservations')->default(0);
            $table->dateTime('latest_return_date')->nullable();
            $table->integer('odometer');
            $table->string('chassis_number')->unique();
            $table->date('license_expiry_date');
            $table->date('insurance_expiry_date');
            $table->text('recent_note')->nullable();
            $table->integer('seats')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
