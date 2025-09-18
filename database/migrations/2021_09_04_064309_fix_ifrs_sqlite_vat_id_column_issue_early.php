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
        // Only run this for SQLite databases
        if (config('database.default') === 'sqlite') {
            // If the IFRS table doesn't exist, skip this fix (e.g., during tests where vendor migrations are not loaded)
            if (!Schema::hasTable('ifrs_line_items')) {
                return;
            }
            
            // Check if vat_id column exists before trying to drop it
            if (!Schema::hasColumn('ifrs_line_items', 'vat_id')) {
                return;
            }
            
            // For SQLite, we need to recreate the table without the vat_id column
            // This is the proper way to "drop" a column with foreign key constraints in SQLite
            
            // First, disable foreign key checks
            DB::statement('PRAGMA foreign_keys=OFF');
            
            // Create a temporary table with the new structure (without vat_id)
            Schema::create('ifrs_line_items_temp', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('transaction_id');
                $table->string('narration', 300);
                $table->decimal('amount', 15, 2);
                $table->decimal('quantity', 15, 4)->default(1);
                $table->boolean('vat_inclusive')->default(false);
                $table->timestamp('destroyed_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->boolean('credited')->default(false);
                
                $table->foreign('entity_id')->references('id')->on('ifrs_entities');
                $table->foreign('account_id')->references('id')->on('ifrs_accounts');
                $table->foreign('transaction_id')->references('id')->on('ifrs_transactions');
                
                $table->index(['entity_id']);
                $table->index(['account_id']);
                $table->index(['transaction_id']);
            });
            
            // Copy data from the original table (excluding vat_id)
            DB::statement('INSERT INTO ifrs_line_items_temp (id, entity_id, account_id, transaction_id, narration, amount, quantity, vat_inclusive, destroyed_at, deleted_at, created_at, updated_at, credited) 
                          SELECT id, entity_id, account_id, transaction_id, narration, amount, quantity, vat_inclusive, destroyed_at, deleted_at, created_at, updated_at, credited FROM ifrs_line_items');
            
            // Drop the original table
            Schema::drop('ifrs_line_items');
            
            // Rename the temporary table to the original name
            DB::statement('ALTER TABLE ifrs_line_items_temp RENAME TO ifrs_line_items');
            
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it's a fix for a package issue
        // The original migration will handle the down operation
    }
};
