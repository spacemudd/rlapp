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
            $table->enum('business_type', ['individual', 'business'])->default('individual')->after('team_id')->comment('Whether customer is individual or business');
            $table->string('business_name')->nullable()->after('business_type')->comment('Business/Company name for business customers');
            $table->string('driver_name')->nullable()->after('business_name')->comment('Driver name for business customers (if different from owner)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'business_name', 'driver_name']);
        });
    }
};
