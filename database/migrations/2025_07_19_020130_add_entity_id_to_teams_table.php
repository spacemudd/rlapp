<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use IFRS\Models\Entity;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->nullable()->after('description');
            $table->foreign('entity_id')->references('id')->on('ifrs_entities')->onDelete('cascade');
            $table->index('entity_id');
        });
        
        // Set existing teams to use the default IFRS entity
        $defaultEntity = Entity::first();
        if ($defaultEntity) {
            DB::table('teams')->update(['entity_id' => $defaultEntity->id]);
            
            // Make the column not nullable after setting the values
            Schema::table('teams', function (Blueprint $table) {
                $table->unsignedBigInteger('entity_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
        });
    }
};
