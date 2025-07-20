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
        Schema::create('customer_block_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->enum('action', ['blocked', 'unblocked']);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('performed_by_user_id');
            $table->timestamp('performed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('performed_by_user_id')->references('id')->on('users')->onDelete('restrict');
            
            $table->index(['customer_id', 'performed_at']);
            $table->index(['action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_block_history');
    }
};
