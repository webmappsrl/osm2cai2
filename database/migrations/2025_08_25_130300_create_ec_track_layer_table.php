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
        Schema::create('ec_track_layer', function (Blueprint $table) {
            $table->id();
            $table->integer('ec_track_id'); // Come richiesto dal WMPackage
            $table->integer('layer_id');
            $table->index(['ec_track_id', 'layer_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ec_track_layer');
    }
};
