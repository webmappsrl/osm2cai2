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
            $table->json('tags')->nullable();
        });
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->geometry('geometry_osm', 'MultiLineString', 4326)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropColumn('tags');
        });

        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('geometry_osm');
        });
    }
};
