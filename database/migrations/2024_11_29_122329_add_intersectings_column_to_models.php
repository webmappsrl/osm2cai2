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
        // add the intersectings column to hiking routes if not exists
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to huts if not exists
        Schema::table('cai_huts', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to areas if not exists
        Schema::table('areas', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to clubs if not exists
        Schema::table('clubs', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to ec_pois if not exists
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to itineraries if not exists
        Schema::table('itineraries', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to natural_springs if not exists
        Schema::table('natural_springs', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to municipalities if not exists
        Schema::table('municipalities', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to poles if not exists
        Schema::table('poles', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to provinces if not exists
        Schema::table('provinces', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });

        // add the intersectings column to sectors if not exists
        Schema::table('sectors', function (Blueprint $table) {
            $table->jsonb('intersectings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('cai_huts', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('itineraries', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('natural_springs', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('poles', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('intersectings');
        });
    }
};
