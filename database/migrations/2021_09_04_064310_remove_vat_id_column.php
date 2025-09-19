<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveVatIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if the table and column exist before trying to drop them
        if (!Schema::hasTable(config('ifrs.table_prefix') . 'line_items')) {
            return;
        }
        
        if (!Schema::hasColumn(config('ifrs.table_prefix') . 'line_items', 'vat_id')) {
            return;
        }
        
        Schema::table(config('ifrs.table_prefix') . 'line_items', function (Blueprint $table) {
            
            if (!in_array(config('database.default'), ['sqlite', 'testing'])) {
                $table->dropForeign(config('ifrs.table_prefix') . 'line_items_vat_id_foreign'); // sqlite does not support dropping foregn keys
            }
            $table->dropColumn('vat_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('ifrs.table_prefix') . 'line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('vat_id')->nullable();  
            $table->foreign('vat_id')->references('id')->on(config('ifrs.table_prefix') . 'vats');

        });
    }
}
