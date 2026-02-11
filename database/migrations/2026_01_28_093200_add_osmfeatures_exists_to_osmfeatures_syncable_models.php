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
        // HikingRoute
        Schema::table('hiking_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('hiking_routes', 'osmfeatures_exists')) {
                $table->boolean('osmfeatures_exists')->nullable()->default(true);
            }
        });

        // Poles
        Schema::table('poles', function (Blueprint $table) {
            if (! Schema::hasColumn('poles', 'osmfeatures_exists')) {
                $table->boolean('osmfeatures_exists')->nullable()->default(true);
            }
        });

        // Region
        Schema::table('regions', function (Blueprint $table) {
            if (! Schema::hasColumn('regions', 'osmfeatures_exists')) {
                $table->boolean('osmfeatures_exists')->nullable()->default(true);
            }
        });

        // Province
        Schema::table('provinces', function (Blueprint $table) {
            if (! Schema::hasColumn('provinces', 'osmfeatures_exists')) {
                $table->boolean('osmfeatures_exists')->nullable()->default(true);
            }
        });

        // Municipality
        Schema::table('municipalities', function (Blueprint $table) {
            if (! Schema::hasColumn('municipalities', 'osmfeatures_exists')) {
                $table->boolean('osmfeatures_exists')->nullable()->default(true);
            }
        });

        // CaiHut (non più tracciato con osmfeatures_exists)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            if (Schema::hasColumn('hiking_routes', 'osmfeatures_exists')) {
                $table->dropColumn('osmfeatures_exists');
            }
        });

        Schema::table('poles', function (Blueprint $table) {
            if (Schema::hasColumn('poles', 'osmfeatures_exists')) {
                $table->dropColumn('osmfeatures_exists');
            }
        });

        Schema::table('regions', function (Blueprint $table) {
            if (Schema::hasColumn('regions', 'osmfeatures_exists')) {
                $table->dropColumn('osmfeatures_exists');
            }
        });

        Schema::table('provinces', function (Blueprint $table) {
            if (Schema::hasColumn('provinces', 'osmfeatures_exists')) {
                $table->dropColumn('osmfeatures_exists');
            }
        });

        Schema::table('municipalities', function (Blueprint $table) {
            if (Schema::hasColumn('municipalities', 'osmfeatures_exists')) {
                $table->dropColumn('osmfeatures_exists');
            }
        });

        // CaiHut (non più tracciato con osmfeatures_exists)
    }
};
