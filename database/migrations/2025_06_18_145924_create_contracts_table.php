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
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->enum('status', ['draft', 'active', 'completed', 'void'])->default('draft');
            
            // Relationships
            $table->foreignUuid('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->onDelete('restrict');
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            
            // Contract dates
            $table->date('start_date');
            $table->date('end_date');
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            
            // Financial details
            $table->decimal('total_amount', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('daily_rate', 10, 2);
            $table->integer('total_days');
            $table->string('currency', 3)->default('AED');
            
            // Additional terms
            $table->integer('mileage_limit')->nullable();
            $table->decimal('excess_mileage_rate', 8, 2)->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            
            // Tracking
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->text('void_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'team_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
