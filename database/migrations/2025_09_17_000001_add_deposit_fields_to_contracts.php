<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('deposit_type', ['refundable', 'non_refundable'])->nullable()->after('deposit_amount');
            $table->dateTime('deposit_received_at')->nullable()->after('deposit_type');
            $table->string('deposit_payment_method')->nullable()->after('deposit_received_at');
            $table->string('deposit_third_party_name')->nullable()->after('deposit_payment_method');
            $table->dateTime('deposit_posted_at')->nullable()->after('deposit_third_party_name');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_type',
                'deposit_received_at',
                'deposit_payment_method',
                'deposit_third_party_name',
                'deposit_posted_at',
            ]);
        });
    }
};


