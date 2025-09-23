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
            $table->uuid('ifrs_vat_account_id')->nullable()->after('status')->comment('IFRS Account ID for VAT payable for this branch');
            $table->index('ifrs_vat_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['ifrs_vat_account_id']);
            $table->dropColumn('ifrs_vat_account_id');
        });
    }
};


