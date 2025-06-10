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
        Schema::create('layers', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
            $table->geography('geometry', 'polygon')->nullable()->comment('The bbox of the layer');
            $table->jsonb('properties');
            $table->string('feature_collection')->nullable();
            $table->jsonb('configuration')->nullable();
            $table->integer('app_id');
            $table->integer('rank')->default(0);

            $table->timestamps();

            $table->index('app_id');
            $table->spatialIndex('geometry');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('layers');
    }
};
