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
        Schema::table('customers', function (Blueprint $table) {
            // VAT Registration Information
            $table->string('vat_number', 20)->nullable()->comment('Customer VAT registration number (UAE TRN)');
            $table->boolean('vat_registered')->default(false)->comment('Whether customer is VAT registered');
            $table->date('vat_registration_date')->nullable()->comment('Date of VAT registration');
            $table->string('vat_registration_country', 3)->default('AE')->comment('Country of VAT registration (ISO code)');
            
            // Customer Classification for VAT
            $table->string('customer_type')->default('local')->comment('Customer type: local, export, gcc, other');
            $table->boolean('reverse_charge_applicable')->default(false)->comment('Whether reverse charge mechanism applies');
            $table->string('tax_classification')->nullable()->comment('Customer tax classification');
            
            // Compliance and Validation
            $table->boolean('vat_number_validated')->default(false)->comment('Whether VAT number has been validated');
            $table->timestamp('vat_number_validated_at')->nullable()->comment('When VAT number was last validated');
            $table->json('vat_validation_response')->nullable()->comment('VAT number validation response data');
            
            // Additional VAT Information
            $table->text('vat_notes')->nullable()->comment('Additional VAT-related notes');
            
            // Indexing for performance
            $table->index(['vat_registered', 'customer_type']);
            $table->index(['vat_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex(['vat_registered', 'customer_type']);
            $table->dropIndex(['vat_number']);
            
            // Remove VAT fields
            $table->dropColumn([
                'vat_number',
                'vat_registered',
                'vat_registration_date',
                'vat_registration_country',
                'customer_type',
                'reverse_charge_applicable',
                'tax_classification',
                'vat_number_validated',
                'vat_number_validated_at',
                'vat_validation_response',
                'vat_notes',
            ]);
        });
    }
};
