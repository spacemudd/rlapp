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
        // Update the enum column to include 'expired' status
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'canceled', 'expired') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'expired' from the enum column
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'canceled') NOT NULL DEFAULT 'pending'");
    }
};
