<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Layer;

class CreateLayerAssociatedApp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('layer_associated_app', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Layer::class);
            $table->foreignIdFor(App::class);
            $table->timestamps();

            $table->index('layer_id');
            $table->index('app_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('layer_associated_app');
    }
}
