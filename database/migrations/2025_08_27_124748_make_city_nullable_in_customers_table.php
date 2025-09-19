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
        if (Schema::hasColumn('customers', 'city')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('city')->nullable()->change();
            });
        } else {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('city')->nullable()->after('country');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'city')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('city');
            });
        }
    }
};
