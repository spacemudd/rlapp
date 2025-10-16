<?php

declare(strict_types=1);

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
        Schema::create('contract_additional_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('fee_type');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->unsignedBigInteger('vat_account_id')->nullable();
            $table->foreign('vat_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->boolean('is_vat_exempt')->default(false);
            $table->decimal('total', 10, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for performance
            $table->index('contract_id');
            $table->index('fee_type');
            $table->index(['contract_id', 'fee_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_additional_fees');
    }
};

