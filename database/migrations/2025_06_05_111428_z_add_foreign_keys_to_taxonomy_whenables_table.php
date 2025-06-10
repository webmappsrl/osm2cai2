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
        Schema::table('taxonomy_whenables', function (Blueprint $table) {
            $table->foreign(['taxonomy_when_id'])->references(['id'])->on('taxonomy_whens');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('taxonomy_whenables', function (Blueprint $table) {
            $table->dropForeign('taxonomy_whenables_taxonomy_when_id_foreign');
        });
    }
};
