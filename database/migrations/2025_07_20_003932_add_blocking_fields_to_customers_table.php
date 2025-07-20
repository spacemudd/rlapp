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
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('status');
            $table->string('block_reason')->nullable()->after('is_blocked');
            $table->timestamp('blocked_at')->nullable()->after('block_reason');
            $table->unsignedBigInteger('blocked_by_user_id')->nullable()->after('blocked_at');
            
            $table->foreign('blocked_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['is_blocked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['blocked_by_user_id']);
            $table->dropIndex(['is_blocked']);
            $table->dropColumn(['is_blocked', 'block_reason', 'blocked_at', 'blocked_by_user_id']);
        });
    }
};
