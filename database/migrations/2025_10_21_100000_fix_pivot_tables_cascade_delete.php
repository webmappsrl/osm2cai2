<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix foreign keys in pivot tables to use CASCADE DELETE instead of SET NULL.
     * This resolves the issue where deleting entities (EcPoi, MountainGroup, etc.)
     * fails because the database tries to set NOT NULL columns to NULL.
     */
    public function up(): void
    {
        // ================================================================
        // MOUNTAIN GROUP PIVOT TABLES
        // ================================================================

        // 1. mountain_group_cai_hut
        Schema::table('mountain_group_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('cascade');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('cascade');
        });

        // 2. mountain_group_ec_poi (PRINCIPALE - causa dell'errore ID 16033)
        Schema::table('mountain_group_ec_poi', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['ec_poi_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('cascade');

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('cascade');
        });

        // 3. mountain_group_club
        Schema::table('mountain_group_club', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['club_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('cascade');

            $table->foreign('club_id')
                ->references('id')
                ->on('clubs')
                ->onDelete('cascade');
        });

        // 4. mountain_group_hiking_route
        Schema::table('mountain_group_hiking_route', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['hiking_route_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('cascade');

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('cascade');
        });

        // ================================================================
        // EC POI PIVOT TABLES
        // ================================================================

        // 5. hiking_route_ec_poi
        Schema::table('hiking_route_ec_poi', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['ec_poi_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('cascade');

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('cascade');
        });

        // 6. ec_poi_club
        Schema::table('ec_poi_club', function (Blueprint $table) {
            $table->dropForeign(['ec_poi_id']);
            $table->dropForeign(['club_id']);

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('cascade');

            $table->foreign('club_id')
                ->references('id')
                ->on('clubs')
                ->onDelete('cascade');
        });

        // 7. ec_poi_cai_hut
        Schema::table('ec_poi_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['ec_poi_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('cascade');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('cascade');
        });

        // ================================================================
        // HIKING ROUTE PIVOT TABLES
        // ================================================================

        // 8. hiking_route_cai_hut
        Schema::table('hiking_route_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('cascade');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('cascade');
        });

        // 9. hiking_route_natural_spring
        Schema::table('hiking_route_natural_spring', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['natural_spring_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('cascade');

            $table->foreign('natural_spring_id')
                ->references('id')
                ->on('natural_springs')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: Reverting will restore the original buggy behavior
     * where deleting entities fails due to SET NULL on NOT NULL columns.
     */
    public function down(): void
    {
        // Restore original foreign keys with SET NULL behavior

        Schema::table('mountain_group_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('set null');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('set null');
        });

        Schema::table('mountain_group_ec_poi', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['ec_poi_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('set null');

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('set null');
        });

        Schema::table('mountain_group_club', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['club_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('set null');

            $table->foreign('club_id')
                ->references('id')
                ->on('clubs')
                ->onDelete('set null');
        });

        Schema::table('mountain_group_hiking_route', function (Blueprint $table) {
            $table->dropForeign(['mountain_group_id']);
            $table->dropForeign(['hiking_route_id']);

            $table->foreign('mountain_group_id')
                ->references('id')
                ->on('mountain_groups')
                ->onDelete('set null');

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('set null');
        });

        Schema::table('hiking_route_ec_poi', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['ec_poi_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('set null');

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('set null');
        });

        Schema::table('ec_poi_club', function (Blueprint $table) {
            $table->dropForeign(['ec_poi_id']);
            $table->dropForeign(['club_id']);

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('set null');

            $table->foreign('club_id')
                ->references('id')
                ->on('clubs')
                ->onDelete('set null');
        });

        Schema::table('ec_poi_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['ec_poi_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('ec_poi_id')
                ->references('id')
                ->on('ec_pois')
                ->onDelete('set null');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('set null');
        });

        Schema::table('hiking_route_cai_hut', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['cai_hut_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('set null');

            $table->foreign('cai_hut_id')
                ->references('id')
                ->on('cai_huts')
                ->onDelete('set null');
        });

        Schema::table('hiking_route_natural_spring', function (Blueprint $table) {
            $table->dropForeign(['hiking_route_id']);
            $table->dropForeign(['natural_spring_id']);

            $table->foreign('hiking_route_id')
                ->references('id')
                ->on('hiking_routes')
                ->onDelete('set null');

            $table->foreign('natural_spring_id')
                ->references('id')
                ->on('natural_springs')
                ->onDelete('set null');
        });
    }
};
