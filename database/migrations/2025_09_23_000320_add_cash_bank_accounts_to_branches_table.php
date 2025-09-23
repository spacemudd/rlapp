<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('ifrs_cash_account_id')->nullable()->after('ifrs_vat_account_id');
            $table->unsignedBigInteger('ifrs_bank_account_id')->nullable()->after('ifrs_cash_account_id');
            
            $table->foreign('ifrs_cash_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            $table->foreign('ifrs_bank_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['ifrs_cash_account_id']);
            $table->dropForeign(['ifrs_bank_account_id']);
            $table->dropColumn(['ifrs_cash_account_id', 'ifrs_bank_account_id']);
        });
    }
};