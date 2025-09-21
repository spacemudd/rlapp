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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'TikTok', 'Snapchat', 'Instagram'
            $table->string('slug')->unique(); // e.g., 'tiktok', 'snapchat', 'instagram'
            $table->boolean('is_custom')->default(false); // true for user-defined sources
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
