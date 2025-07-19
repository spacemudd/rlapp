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
        Schema::table('invoice_items', function (Blueprint $table) {
            // VAT Treatment and Calculation
            $table->string('vat_treatment')->default('standard')->comment('VAT treatment: standard, zero_rated, exempt, out_of_scope');
            $table->decimal('vat_rate', 5, 2)->nullable()->comment('VAT rate applied to this item');
            $table->decimal('amount_excluding_vat', 15, 2)->nullable()->comment('Item amount excluding VAT');
            $table->decimal('vat_amount', 15, 2)->default(0)->comment('VAT amount for this item');
            $table->decimal('amount_including_vat', 15, 2)->nullable()->comment('Item amount including VAT');
            
            // Item Classification
            $table->string('item_category')->nullable()->comment('Item category for VAT purposes');
            $table->boolean('vat_exempt_reason')->nullable()->comment('Reason for VAT exemption if applicable');
            
            // Additional VAT Information
            $table->text('vat_notes')->nullable()->comment('Line-item specific VAT notes');
            
            // Indexing for performance
            $table->index(['vat_treatment']);
            $table->index(['vat_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex(['vat_treatment']);
            $table->dropIndex(['vat_rate']);
            
            // Remove VAT fields
            $table->dropColumn([
                'vat_treatment',
                'vat_rate',
                'amount_excluding_vat',
                'vat_amount',
                'amount_including_vat',
                'item_category',
                'vat_exempt_reason',
                'vat_notes',
            ]);
        });
    }
};
