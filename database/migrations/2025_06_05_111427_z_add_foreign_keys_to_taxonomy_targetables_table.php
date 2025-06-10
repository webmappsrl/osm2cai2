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
        Schema::table('taxonomy_targetables', function (Blueprint $table) {
            $table->foreign(['taxonomy_target_id'])->references(['id'])->on('taxonomy_targets');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('taxonomy_targetables', function (Blueprint $table) {
            $table->dropForeign('taxonomy_targetables_taxonomy_target_id_foreign');
        });
    }
};
