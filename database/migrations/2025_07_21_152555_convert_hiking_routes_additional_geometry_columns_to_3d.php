<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Convert geometry_osm column to 3D (if it exists and has data)
            $hasGeometryOsm = Schema::hasColumn('hiking_routes', 'geometry_osm');

            if ($hasGeometryOsm) {
                // Check if there are records with geometry_osm data
                $hasData = DB::table('hiking_routes')->whereNotNull('geometry_osm')->exists();

                if ($hasData) {
                    // 1. Add a temporary 3D geometry_osm column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->geometry('geometry_osm_temp', 'MultiLineStringZ', 4326)->nullable();
                    });

                    // 2. Update the temporary column with 3D geometries from the old column
                    DB::statement('UPDATE hiking_routes SET geometry_osm_temp = ST_Force3D(geometry_osm) WHERE geometry_osm IS NOT NULL');

                    // 3. Drop the old 2D geometry_osm column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->dropColumn('geometry_osm');
                    });

                    // 4. Rename the temporary column to the final name
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->renameColumn('geometry_osm_temp', 'geometry_osm');
                    });
                }
            }

            // Convert geometry_raw_data column to 3D (if it exists and has data)
            $hasGeometryRawData = Schema::hasColumn('hiking_routes', 'geometry_raw_data');

            if ($hasGeometryRawData) {
                // Check if there are records with geometry_raw_data data
                $hasData = DB::table('hiking_routes')->whereNotNull('geometry_raw_data')->exists();

                if ($hasData) {
                    // 1. Add a temporary 3D geometry_raw_data column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->geometry('geometry_raw_data_temp', 'MultiLineStringZ', 4326)->nullable();
                    });

                    // 2. Update the temporary column with 3D geometries from the old column
                    DB::statement('UPDATE hiking_routes SET geometry_raw_data_temp = ST_Force3D(geometry_raw_data) WHERE geometry_raw_data IS NOT NULL');

                    // 3. Drop the old 2D geometry_raw_data column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->dropColumn('geometry_raw_data');
                    });

                    // 4. Rename the temporary column to the final name
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->renameColumn('geometry_raw_data_temp', 'geometry_raw_data');
                    });
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Revert geometry_osm column to 2D (if it exists)
            $hasGeometryOsm = Schema::hasColumn('hiking_routes', 'geometry_osm');

            if ($hasGeometryOsm) {
                // Check if there are records with geometry_osm data
                $hasData = DB::table('hiking_routes')->whereNotNull('geometry_osm')->exists();

                if ($hasData) {
                    // 1. Add a temporary 2D geometry_osm column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->geometry('geometry_osm_temp', 'MultiLineString', 4326)->nullable();
                    });

                    // 2. Update the temporary column with 2D geometries from the old column
                    DB::statement('UPDATE hiking_routes SET geometry_osm_temp = ST_Force2D(geometry_osm) WHERE geometry_osm IS NOT NULL');

                    // 3. Drop the old 3D geometry_osm column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->dropColumn('geometry_osm');
                    });

                    // 4. Rename the temporary column to the final name
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->renameColumn('geometry_osm_temp', 'geometry_osm');
                    });
                }
            }

            // Revert geometry_raw_data column to 2D (if it exists)
            $hasGeometryRawData = Schema::hasColumn('hiking_routes', 'geometry_raw_data');

            if ($hasGeometryRawData) {
                // Check if there are records with geometry_raw_data data
                $hasData = DB::table('hiking_routes')->whereNotNull('geometry_raw_data')->exists();

                if ($hasData) {
                    // 1. Add a temporary 2D geometry_raw_data column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->geometry('geometry_raw_data_temp', 'MultiLineString', 4326)->nullable();
                    });

                    // 2. Update the temporary column with 2D geometries from the old column
                    DB::statement('UPDATE hiking_routes SET geometry_raw_data_temp = ST_Force2D(geometry_raw_data) WHERE geometry_raw_data IS NOT NULL');

                    // 3. Drop the old 3D geometry_raw_data column
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->dropColumn('geometry_raw_data');
                    });

                    // 4. Rename the temporary column to the final name
                    Schema::table('hiking_routes', function (Blueprint $table) {
                        $table->renameColumn('geometry_raw_data_temp', 'geometry_raw_data');
                    });
                }
            }
        });
    }
};
