<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_receipt_allocations', function (Blueprint $table) {
            $table->string('allocation_type')->nullable()->after('description'); // security_deposit, advance_payment, invoice_settlement
            $table->foreignUuid('invoice_id')->nullable()->after('allocation_type');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');

            $table->index(['allocation_type'], 'pra_allocation_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipt_allocations', function (Blueprint $table) {
            $table->dropIndex('pra_allocation_type_idx');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('allocation_type');
        });
    }
};


