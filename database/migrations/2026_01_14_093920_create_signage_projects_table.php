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
        Schema::create('signage_projects', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
            $table->geography('geometry', 'polygon')->nullable()->comment('The bbox of the signage project');
            $table->jsonb('properties')->nullable();
            $table->integer('app_id')->default(1)->comment('Sempre 1, stessa app delle hiking routes');
            $table->integer('user_id')->nullable()->comment('Proprietario: sempre chi crea il progetto');

            $table->timestamps();

            $table->index('user_id');
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
        Schema::dropIfExists('signage_projects');
    }
};
