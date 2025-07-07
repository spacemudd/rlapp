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
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->string('car_name');
            $table->string('plate_code');
            $table->string('plate_number');
            $table->dateTime('dateandtime');
            $table->string('location')->nullable();
            $table->string('source')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('fine_number')->unique();
            $table->text('details')->nullable();
            $table->boolean('dispute')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
