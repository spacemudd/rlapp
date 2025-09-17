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
        // For SQLite tests, use check constraint instead of ENUM alterations
        if (config('database.default') === 'sqlite') {
            // Skip altering enum for SQLite in tests; ensure column exists
            if (!Schema::hasColumn('reservations', 'status')) {
                Schema::table('reservations', function (Blueprint $table) {
                    $table->string('status')->default('pending');
                });
            }
            return;
        }
        // Update the enum column to include 'expired' status for MySQL
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'canceled', 'expired') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }
        // Remove 'expired' from the enum column for MySQL
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'canceled') NOT NULL DEFAULT 'pending'");
    }
};
