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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('customers', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('status');
            }
            
            if (!Schema::hasColumn('customers', 'block_reason')) {
                $table->string('block_reason')->nullable()->after('is_blocked');
            }
            
            if (!Schema::hasColumn('customers', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('block_reason');
            }
            
            if (!Schema::hasColumn('customers', 'blocked_by_user_id')) {
                $table->unsignedBigInteger('blocked_by_user_id')->nullable()->after('blocked_at');
            }
        });
        
        // Add foreign key constraint if it doesn't exist
        if (Schema::hasColumn('customers', 'blocked_by_user_id')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->foreign('blocked_by_user_id')->references('id')->on('users')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }
        }
        
        // Add index if it doesn't exist
        if (Schema::hasColumn('customers', 'is_blocked')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->index(['is_blocked']);
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'blocked_by_user_id')) {
                $table->dropForeign(['blocked_by_user_id']);
            }
            
            if (Schema::hasColumn('customers', 'is_blocked')) {
                $table->dropIndex(['is_blocked']);
            }
            
            $table->dropColumn(['is_blocked', 'block_reason', 'blocked_at', 'blocked_by_user_id']);
        });
    }
};
