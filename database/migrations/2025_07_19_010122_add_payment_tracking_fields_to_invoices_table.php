<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add payment tracking fields
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount')->comment('Total amount paid on this invoice');
            $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount')->comment('Remaining amount to be paid');
            
            // Add additional fields that the model expects
            $table->date('issue_date')->nullable()->after('invoice_date')->comment('Date when invoice was issued');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('total_discount')->comment('Tax amount on invoice');
            $table->text('notes')->nullable()->after('remaining_amount')->comment('Invoice notes');
            
            // Add indexes for performance
            $table->index(['status', 'remaining_amount']);
            $table->index(['due_date', 'remaining_amount']);
            $table->index(['customer_id', 'status']);
        });
        
        // Update existing invoices to set remaining_amount = total_amount for unpaid invoices
        DB::statement('UPDATE invoices SET remaining_amount = total_amount WHERE status IN ("unpaid", "partial", "partial_paid")');
        DB::statement('UPDATE invoices SET remaining_amount = 0 WHERE status IN ("paid", "fully_paid")');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['status', 'remaining_amount']);
            $table->dropIndex(['due_date', 'remaining_amount']);
            $table->dropIndex(['customer_id', 'status']);
            
            // Drop the columns
            $table->dropColumn([
                'paid_amount',
                'remaining_amount',
                'issue_date', 
                'tax_amount',
                'notes',
            ]);
        });
    }
};
