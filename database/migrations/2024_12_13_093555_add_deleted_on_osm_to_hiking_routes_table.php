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
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->boolean('deleted_on_osm')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('deleted_on_osm');
        });
    }
};
