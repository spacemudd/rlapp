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
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 10)->unique(); // Short code for the cash account
            $table->enum('type', ['petty_cash', 'cash_register', 'checks_received', 'checks_issued', 'other'])->default('petty_cash');
            $table->string('location')->nullable(); // Physical location of cash
            $table->string('currency', 3)->default('AED');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('limit_amount', 15, 2)->nullable(); // Maximum allowed balance
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('ifrs_account_id')->nullable(); // Link to IFRS account
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('responsible_person')->nullable(); // Who manages this cash account
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign key to IFRS accounts table
            $table->foreign('ifrs_account_id')->references('id')->on('ifrs_accounts')->onDelete('set null');
            
            // Indexes
            $table->index(['team_id']);
            $table->index(['is_active']);
            $table->index(['type']);
            $table->index(['ifrs_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
