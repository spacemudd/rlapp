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
        // Remove invoice_id from contracts table (one-to-one relationship)
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
        });

        // Add contract_id to invoices table (one-to-many relationship)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('contract_id')->nullable()->after('customer_id')->constrained('contracts')->onDelete('set null');
            $table->index('contract_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove contract_id from invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropIndex(['contract_id']);
            $table->dropColumn('contract_id');
        });

        // Add invoice_id back to contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->nullable()->after('team_id')->constrained('invoices')->onDelete('set null');
        });
    }
};
