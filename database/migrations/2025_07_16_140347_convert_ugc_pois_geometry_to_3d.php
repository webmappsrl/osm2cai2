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
            // 1. Add a temporary 3D geometry column
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->geography('geometry_temp', 'pointz', 4326)->nullable();
            });

            // 2. Update the temporary column with 3D geometries from the old column
            DB::statement('UPDATE ugc_pois SET geometry_temp = ST_Force3D(geometry::geometry)::geography WHERE geometry IS NOT NULL');

            // 3. Drop the old 2D geometry column
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->dropColumn('geometry');
            });

            // 4. Rename the temporary column to the final name
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->renameColumn('geometry_temp', 'geometry');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // 1. Add a temporary 2D geometry column
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->geography('geometry_temp', 'point', 4326)->nullable();
            });

            // 2. Update the temporary column with 2D geometries from the old column
            DB::statement('UPDATE ugc_pois SET geometry_temp = ST_Force2D(geometry::geometry)::geography WHERE geometry IS NOT NULL');

            // 3. Drop the old 3D geometry column
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->dropColumn('geometry');
            });

            // 4. Rename the temporary column to the final name
            Schema::table('ugc_pois', function (Blueprint $table) {
                $table->renameColumn('geometry_temp', 'geometry');
            });
        });
    }
};
