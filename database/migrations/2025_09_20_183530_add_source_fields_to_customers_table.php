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
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_referral')->nullable(); // For custom referrals like "Ahmed's customer"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropColumn(['source_id', 'custom_referral']);
        });
    }
};
