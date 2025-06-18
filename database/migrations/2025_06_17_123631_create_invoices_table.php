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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->dateTime('invoice_date');
            $table->dateTime('due_date');
            $table->enum('status', ['paid', 'unpaid', 'partial', 'partial_paid', 'fully_paid'])->default('unpaid');
            $table->string('currency', 3)->default('AED');
            $table->integer('total_days');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->onDelete('restrict');
            $table->foreignUuid('customer_id')->constrained('customers')->onDelete('restrict');
            $table->decimal('sub_total', 10, 2);
            $table->decimal('total_discount', 10, 2)->default('0');
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
