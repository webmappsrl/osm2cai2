<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->json('properties')->nullable();
            $table->integer('app_id')->nullable();
            $table->bigInteger('osmid')->nullable();
        });

        // Aggiungi indici dopo aver creato le colonne
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->index('osmid');
            $table->index('app_id');
            $table->spatialIndex('geometry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropIndex(['osmid']);
            $table->dropIndex(['app_id']);
            $table->dropSpatialIndex(['geometry']);
        });

        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropColumn(['properties', 'app_id', 'osmid']);
        });
    }
};
