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
        Schema::table('area_hiking_route', function (Blueprint $table) {
            $table->float('percentage')->default(0);
        });
        Schema::table('hiking_route_province', function (Blueprint $table) {
            $table->float('percentage')->default(0);
        });
        Schema::table('hiking_route_region', function (Blueprint $table) {
            $table->float('percentage')->default(0);
        });
        Schema::table('hiking_route_sector', function (Blueprint $table) {
            $table->float('percentage')->default(0);
        });

        Schema::table('mountain_group_region', function (Blueprint $table) {
            $table->float('percentage')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('area_hiking_route', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
        Schema::table('hiking_route_province', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
        Schema::table('hiking_route_region', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
        Schema::table('hiking_route_sector', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
        Schema::table('mountain_group_region', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
    }
};
