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
        Schema::create('contract_extensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->integer('extension_number')->comment('Sequential number for this contract: 1, 2, 3, etc.');
            $table->dateTime('original_end_date')->comment('End date before this extension');
            $table->dateTime('new_end_date')->comment('New end date after this extension');
            $table->integer('extension_days')->comment('Number of days being added');
            $table->decimal('daily_rate', 10, 2)->comment('Daily rate for this extension');
            $table->decimal('total_amount', 10, 2)->comment('Total amount for this extension');
            $table->text('reason')->nullable()->comment('Reason for extension');
            $table->string('approved_by')->nullable()->comment('User who approved the extension');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_id', 'extension_number']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_extensions');
    }
};
