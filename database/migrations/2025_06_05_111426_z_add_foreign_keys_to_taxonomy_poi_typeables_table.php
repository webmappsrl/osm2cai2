<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('taxonomy_poi_typeables', function (Blueprint $table) {
            $table->foreign(['taxonomy_poi_type_id'])->references(['id'])->on('taxonomy_poi_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('taxonomy_poi_typeables', function (Blueprint $table) {
            $table->dropForeign('taxonomy_poi_typeables_taxonomy_poi_type_id_foreign');
        });
    }
};
