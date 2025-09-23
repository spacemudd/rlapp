<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipt_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_receipt_id')->constrained('payment_receipts')->onDelete('cascade');
            $table->unsignedBigInteger('gl_account_id');
            $table->foreign('gl_account_id')->references('id')->on('ifrs_accounts')->onDelete('cascade');
            
            // Allocation details
            $table->string('row_id'); // violation_guarantee, prepayment, etc.
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->text('memo')->nullable();
            
            // IFRS line item reference
            $table->unsignedBigInteger('ifrs_line_item_id')->nullable();
            $table->foreign('ifrs_line_item_id')->references('id')->on('ifrs_line_items')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['payment_receipt_id', 'gl_account_id'], 'pra_receipt_gl_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipt_allocations');
    }
};
