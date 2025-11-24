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
        Schema::create('trail_survey_ugc_poi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trail_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ugc_poi_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trail_survey_ugc_poi');
    }
};

