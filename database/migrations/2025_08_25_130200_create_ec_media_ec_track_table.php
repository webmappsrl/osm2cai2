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
        Schema::create('ec_media_ec_track', function (Blueprint $table) {
            $table->id();
            $table->integer('ec_media_id');
            $table->integer('ec_track_id'); // Come richiesto dal WMPackage
            $table->index(['ec_media_id', 'ec_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ec_media_ec_track');
    }
};
