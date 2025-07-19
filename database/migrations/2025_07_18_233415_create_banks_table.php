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
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 10)->unique(); // Short code for the bank
            $table->string('account_number')->unique();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_address')->nullable();
            $table->string('currency', 3)->default('AED');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('ifrs_account_id')->nullable(); // Link to IFRS account
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign key to IFRS accounts table
            $table->foreign('ifrs_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            
            // Indexes
            $table->index(['team_id']);
            $table->index(['is_active']);
            $table->index(['ifrs_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
