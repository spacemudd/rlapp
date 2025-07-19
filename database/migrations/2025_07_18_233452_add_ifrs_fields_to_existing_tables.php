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
        // Add IFRS fields to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('ifrs_transaction_id')->nullable()->after('total_amount');
            $table->unsignedBigInteger('ifrs_receivable_account_id')->nullable()->after('ifrs_transaction_id');
            
            $table->foreign('ifrs_transaction_id')->references('id')->on('ifrs_transactions')->onDelete('set null');
            $table->foreign('ifrs_receivable_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            
            $table->index(['ifrs_transaction_id']);
            $table->index(['ifrs_receivable_account_id']);
        });
        
        // Add IFRS fields to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('ifrs_transaction_id')->nullable()->after('transaction_type');
            $table->uuid('bank_id')->nullable()->after('ifrs_transaction_id');
            $table->uuid('cash_account_id')->nullable()->after('bank_id');
            $table->string('check_number')->nullable()->after('cash_account_id');
            $table->date('check_date')->nullable()->after('check_number');
            
            $table->foreign('ifrs_transaction_id')->references('id')->on('ifrs_transactions')->onDelete('set null');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->onDelete('set null');
            
            $table->index(['ifrs_transaction_id']);
            $table->index(['bank_id']);
            $table->index(['cash_account_id']);
        });
        
        // Add IFRS fields to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('ifrs_receivable_account_id')->nullable()->after('notes');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('ifrs_receivable_account_id');
            $table->enum('payment_terms', ['cash', '15_days', '30_days', '60_days', '90_days'])->default('cash')->after('credit_limit');
            
            $table->foreign('ifrs_receivable_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            
            $table->index(['ifrs_receivable_account_id']);
            $table->index(['payment_terms']);
        });
        
        // Add IFRS fields to vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('ifrs_asset_account_id')->nullable()->after('recent_note');
            $table->decimal('acquisition_cost', 15, 2)->nullable()->after('ifrs_asset_account_id');
            $table->date('acquisition_date')->nullable()->after('acquisition_cost');
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'sum_of_years'])->default('straight_line')->after('acquisition_date');
            $table->integer('useful_life_years')->default(5)->after('depreciation_method');
            $table->decimal('salvage_value', 15, 2)->default(0)->after('useful_life_years');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0)->after('salvage_value');
            
            $table->foreign('ifrs_asset_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            
            $table->index(['ifrs_asset_account_id']);
            $table->index(['acquisition_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['ifrs_asset_account_id']);
            $table->dropIndex(['ifrs_asset_account_id']);
            $table->dropIndex(['acquisition_date']);
            $table->dropColumn([
                'ifrs_asset_account_id',
                'acquisition_cost',
                'acquisition_date',
                'depreciation_method',
                'useful_life_years',
                'salvage_value',
                'accumulated_depreciation'
            ]);
        });
        
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['ifrs_receivable_account_id']);
            $table->dropIndex(['ifrs_receivable_account_id']);
            $table->dropIndex(['payment_terms']);
            $table->dropColumn([
                'ifrs_receivable_account_id',
                'credit_limit',
                'payment_terms'
            ]);
        });
        
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['ifrs_transaction_id']);
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['cash_account_id']);
            $table->dropIndex(['ifrs_transaction_id']);
            $table->dropIndex(['bank_id']);
            $table->dropIndex(['cash_account_id']);
            $table->dropColumn([
                'ifrs_transaction_id',
                'bank_id',
                'cash_account_id',
                'check_number',
                'check_date'
            ]);
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['ifrs_transaction_id']);
            $table->dropForeign(['ifrs_receivable_account_id']);
            $table->dropIndex(['ifrs_transaction_id']);
            $table->dropIndex(['ifrs_receivable_account_id']);
            $table->dropColumn([
                'ifrs_transaction_id',
                'ifrs_receivable_account_id'
            ]);
        });
    }
};
