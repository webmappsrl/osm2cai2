<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->integer('app_id')->nullable();
            $table->jsonb('properties')->nullable();
            $table->foreign(['app_id'])->references(['id'])->on('apps');
        });

        // Nota: L'assegnazione di app_id verrà fatta tramite comando Artisan dopo la creazione delle app
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropForeign('hiking_routes_app_id_foreign');
            $table->dropColumn('app_id');
            $table->dropColumn('properties');
        });
    }
};
