<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique();
            $table->foreignUuid('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignUuid('branch_id')->constrained('branches')->onDelete('cascade');
            
            // Payment details
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method'); // cash, card, bank_transfer
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->enum('status', ['completed', 'pending', 'failed'])->default('completed');
            $table->text('notes')->nullable();
            
            // IFRS integration
            $table->unsignedBigInteger('ifrs_transaction_id')->nullable();
            $table->foreign('ifrs_transaction_id')->references('id')->on('ifrs_transactions')->onDelete('set null');
            
            // Bank/Cash account details
            $table->foreignUuid('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_accounts')->onDelete('set null');
            $table->string('check_number')->nullable();
            $table->date('check_date')->nullable();
            
            // Tracking
            $table->string('created_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_id', 'payment_date']);
            $table->index(['customer_id', 'payment_date']);
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
