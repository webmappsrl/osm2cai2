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
        Schema::table('layer_associated_app', function (Blueprint $table) {
            $table->foreign(['layer_id'])->references(['id'])->on('layers')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('layer_associated_app', function (Blueprint $table) {
            $table->dropForeign('layer_associated_app_layer_id_foreign');
        });
    }
};
