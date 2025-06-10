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
        Schema::create('layerables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('layer_id');
            $table->morphs('layerable');
            $table->jsonb('properties');
            $table->timestamps();

            $table->index('layer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('layerables');
    }
};
