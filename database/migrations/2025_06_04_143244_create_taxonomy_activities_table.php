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
        Schema::create('taxonomy_activities', function (Blueprint $table) {
            $table->id('id');
            
            $table->text('name');
            $table->text('description')->nullable();
            $table->string('excerpt')->nullable();
            
            $table->text('identifier')->nullable()->unique();
            $table->timestamps();
            $table->jsonb('properties')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxonomy_activities');
    }
};
