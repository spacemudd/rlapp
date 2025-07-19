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
        Schema::table('invoices', function (Blueprint $table) {
            // VAT Calculation Fields
            $table->decimal('vat_amount', 10, 2)->default(0)->after('total_amount')->comment('VAT amount calculated');
            $table->decimal('vat_rate', 5, 2)->default(5.00)->after('vat_amount')->comment('VAT rate applied (UAE default 5%)');
            $table->decimal('total_including_vat', 15, 2)->nullable()->after('vat_rate')->comment('Total amount including VAT');
            
            // VAT Compliance Fields
            $table->string('vat_treatment')->default('standard')->comment('Default VAT treatment for invoice');
            $table->boolean('vat_reverse_charge')->default(false)->comment('Whether reverse charge mechanism applies');
            $table->boolean('vat_exempt')->default(false)->comment('Whether invoice is VAT exempt');
            
            // VAT Return Integration
            $table->string('vat_period')->nullable()->comment('VAT period this invoice belongs to (YYYY-QQ format)');
            $table->boolean('included_in_vat_return')->default(false)->comment('Whether included in VAT return submission');
            $table->timestamp('vat_return_date')->nullable()->comment('Date when included in VAT return');
            
            // Additional VAT Fields
            $table->text('vat_notes')->nullable()->comment('Additional VAT-related notes');
            $table->json('vat_breakdown')->nullable()->comment('Detailed VAT breakdown by rate');
            
            // Indexing for VAT reporting
            $table->index(['vat_period', 'included_in_vat_return']);
            $table->index(['vat_treatment']);
            $table->index(['invoice_date', 'vat_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex(['vat_period', 'included_in_vat_return']);
            $table->dropIndex(['vat_treatment']);
            $table->dropIndex(['invoice_date', 'vat_amount']);
            
            // Remove the VAT fields we added
            $table->dropColumn([
                'vat_amount',
                'vat_rate',
                'total_including_vat',
                'vat_treatment',
                'vat_reverse_charge',
                'vat_exempt',
                'vat_period',
                'included_in_vat_return',
                'vat_return_date',
                'vat_notes',
                'vat_breakdown',
            ]);
        });
    }
};
