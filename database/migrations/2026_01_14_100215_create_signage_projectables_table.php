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
        Schema::create('signage_projectables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('signage_project_id');
            $table->morphs('signage_projectable');
            $table->timestamps();

            $table->index('signage_project_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('signage_projectables');
    }
};
