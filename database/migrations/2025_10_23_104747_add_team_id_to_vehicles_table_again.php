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
            if (!Schema::hasColumn('vehicles', 'team_id')) {
                $table->uuid('team_id')->nullable()->after('id');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                $table->index('team_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropIndex(['team_id']);
                $table->dropColumn('team_id');
            }
        });
    }
};
