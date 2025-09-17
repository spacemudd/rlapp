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
            // Drop the columns directly
            $table->dropColumn(['status', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add back the columns
            $table->enum('status', ['paid', 'unpaid', 'partial', 'partial_paid', 'fully_paid'])->default('unpaid');
            $table->string('currency', 3)->default('AED');
            
            // Recreate indexes
            $table->index(['status', 'remaining_amount']);
            $table->index(['customer_id', 'status']);
        });
    }
};