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
        // Check if the table exists
        if (!Schema::hasTable('invoices')) {
            return;
        }
        
        $columnsToDrop = [];
        
        // Check which columns exist and need to be dropped
        if (Schema::hasColumn('invoices', 'status')) {
            $columnsToDrop[] = 'status';
        }
        
        if (Schema::hasColumn('invoices', 'currency')) {
            $columnsToDrop[] = 'currency';
        }
        
        // Only drop columns that exist
        if (!empty($columnsToDrop)) {
            // For MySQL, we need to handle this differently due to foreign key constraints
            if (config('database.default') === 'mysql') {
                // Use raw SQL to drop the columns without dropping indexes first
                // MySQL will handle the index automatically when the column is dropped
                $connection = DB::connection();
                
                foreach ($columnsToDrop as $column) {
                    try {
                        $connection->statement("ALTER TABLE invoices DROP COLUMN `{$column}`");
                    } catch (Exception $e) {
                        // Column might not exist, ignore
                    }
                }
            } else {
                // For other databases, use the normal approach
                Schema::table('invoices', function (Blueprint $table) use ($columnsToDrop) {
                    // Drop indexes that reference the status column first
                    if (in_array('status', $columnsToDrop)) {
                        try {
                            $table->dropIndex(['status', 'remaining_amount']);
                        } catch (Exception $e) {
                            // Index might not exist, ignore
                        }
                        
                        try {
                            $table->dropIndex(['customer_id', 'status']);
                        } catch (Exception $e) {
                            // Index might not exist, ignore
                        }
                    }
                    
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
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