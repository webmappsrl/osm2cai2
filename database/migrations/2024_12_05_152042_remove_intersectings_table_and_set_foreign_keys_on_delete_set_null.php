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
        $tables = [
            'areas',
            'ec_pois',
            'clubs',
            'cai_huts',
            'hiking_routes',
            'itineraries',
            'mountain_groups',
            'municipalities',
            'natural_springs',
            'poles',
            'provinces',
            'regions',
            'sectors',
        ];

        foreach ($tables as $table) {
            // remove intersectings column from areas table
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('intersectings');
            });
        }

        // make club_id foreign key on users table on delete set null
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreignId('club_id')->nullable()->change()->constrained('clubs')->onDelete('set null');
        });

        // make province_id foreign key on areas table on delete set null
        Schema::table('areas', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->foreignId('province_id')->nullable()->change()->constrained('provinces')->onDelete('set null');
        });

        // make region_id foreign key on cai_huts table on delete set null
        Schema::table('cai_huts', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->nullable()->change()->constrained('regions')->onDelete('set null');
        });

        // make region_id foreign key on clubs table on delete set null
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->nullable()->change()->constrained('regions')->onDelete('set null');
        });

        // make region_id and user_id foreign key on ec_pois table on delete set null
        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->nullable()->change()->constrained('regions')->onDelete('set null');
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change()->constrained('users')->onDelete('set null');
        });

        // make user_id and validator_id foreign key on hiking_routes table on delete set null
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change()->constrained('users')->onDelete('set null');
            $table->dropForeign(['validator_id']);
            $table->foreignId('validator_id')->nullable()->change()->constrained('users')->onDelete('set null');
        });

        // make region_id in provinces table on delete set null
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->nullable()->change()->constrained('regions')->onDelete('set null');
        });

        // make area_id foreign key on sectors table on delete set null
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->foreignId('area_id')->nullable()->change()->constrained('areas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'areas',
            'ec_pois',
            'clubs',
            'cai_huts',
            'hiking_routes',
            'itineraries',
            'mountain_groups',
            'municipalities',
            'natural_springs',
            'poles',
            'provinces',
            'regions',
            'sectors',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('intersectings')->nullable();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreignId('club_id')->change()->constrained('clubs')->onDelete('cascade');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->foreignId('province_id')->change()->constrained('provinces')->onDelete('cascade');
        });

        Schema::table('cai_huts', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->change()->constrained('regions')->onDelete('cascade');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->change()->constrained('regions')->onDelete('cascade');
        });

        Schema::table('ec_pois', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->change()->constrained('regions')->onDelete('cascade');
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->change()->constrained('users')->onDelete('cascade');
        });

        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->change()->constrained('users')->onDelete('cascade');
            $table->dropForeign(['validator_id']);
            $table->foreignId('validator_id')->change()->constrained('users')->onDelete('cascade');
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreignId('region_id')->change()->constrained('regions')->onDelete('cascade');
        });

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->foreignId('area_id')->change()->constrained('areas')->onDelete('cascade');
        });
    }
};
