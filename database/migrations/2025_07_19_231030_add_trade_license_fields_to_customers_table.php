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
            $table->string('trade_license_number')->nullable()->after('driver_name')->comment('Trade license number for business customers');
            $table->string('trade_license_pdf_path')->nullable()->after('trade_license_number')->comment('Path to uploaded trade license PDF');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['trade_license_number', 'trade_license_pdf_path']);
        });
    }
};
