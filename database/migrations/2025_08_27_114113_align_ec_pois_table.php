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
        Schema::table('ec_pois', function (Blueprint $table) {
            // Modifica il tipo di geometry da point a pointz (3D)
            // Prima rimuoviamo l'indice spaziale esistente
            $table->dropSpatialIndex(['geometry']);
        });

        // Aggiungi una colonna temporanea per la geometry 3D
        DB::statement('ALTER TABLE ec_pois ADD COLUMN geometry_3d geography(POINTZ, 4326)');

        // Copia i dati con conversione 3D
        DB::statement('UPDATE ec_pois SET geometry_3d = ST_Force3D(geometry::geometry)::geography WHERE geometry IS NOT NULL');

        // Rimuovi la colonna vecchia e rinomina quella nuova
        DB::statement('ALTER TABLE ec_pois DROP COLUMN geometry');
        DB::statement('ALTER TABLE ec_pois RENAME COLUMN geometry_3d TO geometry');

        // Modifica il tipo di properties da json a jsonb
        DB::statement('ALTER TABLE ec_pois ALTER COLUMN properties TYPE jsonb USING properties::jsonb');

        // Modifica il tipo di name da string a text
        DB::statement('ALTER TABLE ec_pois ALTER COLUMN name TYPE text');

        Schema::table('ec_pois', function (Blueprint $table) {
            // Aggiungi l'indice spaziale per la nuova geometry
            $table->spatialIndex('geometry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_pois', function (Blueprint $table) {
            // Ripristina il tipo di geometry a point
            $table->dropSpatialIndex(['geometry']);
        });

        // Aggiungi una colonna temporanea per la geometry 2D
        DB::statement('ALTER TABLE ec_pois ADD COLUMN geometry_2d geography(POINT, 4326)');

        // Copia i dati con conversione 2D
        DB::statement('UPDATE ec_pois SET geometry_2d = ST_Force2D(geometry::geometry)::geography WHERE geometry IS NOT NULL');

        // Rimuovi la colonna vecchia e rinomina quella nuova
        DB::statement('ALTER TABLE ec_pois DROP COLUMN geometry');
        DB::statement('ALTER TABLE ec_pois RENAME COLUMN geometry_2d TO geometry');

        // Ripristina il tipo di properties da jsonb a json
        DB::statement('ALTER TABLE ec_pois ALTER COLUMN properties TYPE json USING properties::json');

        // Ripristina il tipo di name da text a string
        DB::statement('ALTER TABLE ec_pois ALTER COLUMN name TYPE varchar(255)');

        Schema::table('ec_pois', function (Blueprint $table) {
            // Ripristina l'indice spaziale
            $table->spatialIndex('geometry');
        });
    }
};
