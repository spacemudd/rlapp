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
        $hasIfrsAccounts = Schema::hasTable('ifrs_accounts');
        $hasIfrsTransactions = Schema::hasTable('ifrs_transactions');

        // Add IFRS fields to invoices table
        Schema::table('invoices', function (Blueprint $table) use ($hasIfrsAccounts, $hasIfrsTransactions) {
            if (!Schema::hasColumn('invoices', 'ifrs_transaction_id')) {
                $table->unsignedBigInteger('ifrs_transaction_id')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'ifrs_receivable_account_id')) {
                $table->unsignedBigInteger('ifrs_receivable_account_id')->nullable()->after('ifrs_transaction_id');
            }

            if ($hasIfrsTransactions) {
                $table->foreign('ifrs_transaction_id')->references('id')->on('ifrs_transactions')->onDelete('set null');
                $table->index(['ifrs_transaction_id']);
            }
            if ($hasIfrsAccounts) {
                $table->foreign('ifrs_receivable_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
                $table->index(['ifrs_receivable_account_id']);
            }
        });
        
        // Add IFRS fields to payments table
        Schema::table('payments', function (Blueprint $table) use ($hasIfrsTransactions) {
            if (!Schema::hasColumn('payments', 'ifrs_transaction_id')) {
                $table->unsignedBigInteger('ifrs_transaction_id')->nullable()->after('transaction_type');
            }
            if (!Schema::hasColumn('payments', 'bank_id')) {
                $table->uuid('bank_id')->nullable()->after('ifrs_transaction_id');
            }
            if (!Schema::hasColumn('payments', 'cash_account_id')) {
                $table->uuid('cash_account_id')->nullable()->after('bank_id');
            }
            if (!Schema::hasColumn('payments', 'check_number')) {
                $table->string('check_number')->nullable()->after('cash_account_id');
            }
            if (!Schema::hasColumn('payments', 'check_date')) {
                $table->date('check_date')->nullable()->after('check_number');
            }
            
            if ($hasIfrsTransactions) {
                $table->foreign('ifrs_transaction_id')->references('id')->on('ifrs_transactions')->onDelete('set null');
                $table->index(['ifrs_transaction_id']);
            }
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->onDelete('set null');
            $table->index(['bank_id']);
            $table->index(['cash_account_id']);
        });
        
        // Add IFRS fields to customers table
        Schema::table('customers', function (Blueprint $table) use ($hasIfrsAccounts) {
            if (!Schema::hasColumn('customers', 'ifrs_receivable_account_id')) {
                $table->unsignedBigInteger('ifrs_receivable_account_id')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->nullable()->after('ifrs_receivable_account_id');
            }
            if (!Schema::hasColumn('customers', 'payment_terms')) {
                $table->enum('payment_terms', ['cash', '15_days', '30_days', '60_days', '90_days'])->default('cash')->after('credit_limit');
            }
            
            if ($hasIfrsAccounts) {
                $table->foreign('ifrs_receivable_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
                $table->index(['ifrs_receivable_account_id']);
            }
            $table->index(['payment_terms']);
        });
        
        // Add IFRS fields to vehicles table
        Schema::table('vehicles', function (Blueprint $table) use ($hasIfrsAccounts) {
            if (!Schema::hasColumn('vehicles', 'ifrs_asset_account_id')) {
                $table->unsignedBigInteger('ifrs_asset_account_id')->nullable()->after('recent_note');
            }
            if (!Schema::hasColumn('vehicles', 'acquisition_cost')) {
                $table->decimal('acquisition_cost', 15, 2)->nullable()->after('ifrs_asset_account_id');
            }
            if (!Schema::hasColumn('vehicles', 'acquisition_date')) {
                $table->date('acquisition_date')->nullable()->after('acquisition_cost');
            }
            if (!Schema::hasColumn('vehicles', 'depreciation_method')) {
                $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'sum_of_years'])->default('straight_line')->after('acquisition_date');
            }
            if (!Schema::hasColumn('vehicles', 'useful_life_years')) {
                $table->integer('useful_life_years')->default(5)->after('depreciation_method');
            }
            if (!Schema::hasColumn('vehicles', 'salvage_value')) {
                $table->decimal('salvage_value', 15, 2)->default(0)->after('useful_life_years');
            }
            if (!Schema::hasColumn('vehicles', 'accumulated_depreciation')) {
                $table->decimal('accumulated_depreciation', 15, 2)->default(0)->after('salvage_value');
            }
            
            if ($hasIfrsAccounts) {
                $table->foreign('ifrs_asset_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
                $table->index(['ifrs_asset_account_id']);
            }
            $table->index(['acquisition_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'ifrs_asset_account_id')) {
                $table->dropForeign(['ifrs_asset_account_id']);
                $table->dropIndex(['ifrs_asset_account_id']);
            }
            if (Schema::hasColumn('vehicles', 'acquisition_date')) {
                $table->dropIndex(['acquisition_date']);
            }
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
            if (Schema::hasColumn('customers', 'ifrs_receivable_account_id')) {
                $table->dropForeign(['ifrs_receivable_account_id']);
                $table->dropIndex(['ifrs_receivable_account_id']);
            }
            if (Schema::hasColumn('customers', 'payment_terms')) {
                $table->dropIndex(['payment_terms']);
            }
            $table->dropColumn([
                'ifrs_receivable_account_id',
                'credit_limit',
                'payment_terms'
            ]);
        });
        
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'ifrs_transaction_id')) {
                $table->dropForeign(['ifrs_transaction_id']);
                $table->dropIndex(['ifrs_transaction_id']);
            }
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['cash_account_id']);
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
            if (Schema::hasColumn('invoices', 'ifrs_transaction_id')) {
                $table->dropForeign(['ifrs_transaction_id']);
                $table->dropIndex(['ifrs_transaction_id']);
            }
            if (Schema::hasColumn('invoices', 'ifrs_receivable_account_id')) {
                $table->dropForeign(['ifrs_receivable_account_id']);
                $table->dropIndex(['ifrs_receivable_account_id']);
            }
            $table->dropColumn([
                'ifrs_transaction_id',
                'ifrs_receivable_account_id'
            ]);
        });
    }
};
