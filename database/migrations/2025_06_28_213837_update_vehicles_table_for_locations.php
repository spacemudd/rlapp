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
        Schema::table('vehicles', function (Blueprint $table) {
            // Remove the current_location column
            $table->dropColumn('current_location');
            
            // Add location_id foreign key
            $table->uuid('location_id')->nullable()->after('status');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            
            $table->index(['location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
            
            // Re-add current_location column
            $table->string('current_location')->nullable()->after('status');
        });
    }
};
