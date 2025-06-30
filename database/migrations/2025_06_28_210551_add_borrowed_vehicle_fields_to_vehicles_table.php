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
            // Ownership status - whether the vehicle is owned or borrowed
            $table->enum('ownership_status', ['owned', 'borrowed'])->default('owned')->after('status');
            
            // Borrowing details (only applicable when ownership_status = 'borrowed')
            $table->string('borrowed_from_office')->nullable()->after('ownership_status');
            $table->text('borrowing_terms')->nullable()->after('borrowed_from_office');
            $table->date('borrowing_start_date')->nullable()->after('borrowing_terms');
            $table->date('borrowing_end_date')->nullable()->after('borrowing_start_date');
            $table->text('borrowing_notes')->nullable()->after('borrowing_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'ownership_status',
                'borrowed_from_office',
                'borrowing_terms',
                'borrowing_start_date',
                'borrowing_end_date',
                'borrowing_notes'
            ]);
        });
    }
};
