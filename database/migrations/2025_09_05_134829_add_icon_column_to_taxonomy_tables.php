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
        // Aggiunge colonna icon alle tabelle delle tassonomie che non ce l'hanno
        $tables = [
            'taxonomy_activities',
            'taxonomy_targets',
            'taxonomy_whens',
            'taxonomy_poi_types',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'icon')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('icon')->nullable()->after('excerpt');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rimuove colonna icon dalle tabelle delle tassonomie
        $tables = [
            'taxonomy_activities',
            'taxonomy_targets',
            'taxonomy_whens',
            'taxonomy_poi_types',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'icon')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('icon');
                });
            }
        }
    }
};
