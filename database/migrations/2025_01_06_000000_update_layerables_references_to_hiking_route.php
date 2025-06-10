<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Aggiorna tutti i riferimenti da App\Models\EcTrack a App\Models\HikingRoute
        // nella tabella layerables
        DB::table('layerables')
            ->where('layerable_type', 'App\\Models\\EcTrack')
            ->update(['layerable_type' => 'App\\Models\\HikingRoute']);

        // Aggiorna i riferimenti nelle tabelle taxonomy con i nomi di colonna corretti
        $taxonomyUpdates = [
            'taxonomy_activityables' => 'taxonomy_activityable_type',
            'taxonomy_poi_typeables' => 'taxonomy_poi_typeable_type',
            'taxonomy_targetables' => 'taxonomy_targetable_type',
            'taxonomy_whenables' => 'taxonomy_whenable_type',
            'taxonomy_themeables' => 'taxonomy_themeable_type'
        ];

        foreach ($taxonomyUpdates as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)
                    ->where($column, 'App\\Models\\EcTrack')
                    ->update([$column => 'App\\Models\\HikingRoute']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina i riferimenti da App\Models\HikingRoute a App\Models\EcTrack
        DB::table('layerables')
            ->where('layerable_type', 'App\\Models\\HikingRoute')
            ->update(['layerable_type' => 'App\\Models\\EcTrack']);

        // Ripristina i riferimenti nelle tabelle taxonomy
        $taxonomyUpdates = [
            'taxonomy_activityables' => 'taxonomy_activityable_type',
            'taxonomy_poi_typeables' => 'taxonomy_poi_typeable_type',
            'taxonomy_targetables' => 'taxonomy_targetable_type',
            'taxonomy_whenables' => 'taxonomy_whenable_type',
            'taxonomy_themeables' => 'taxonomy_themeable_type'
        ];

        foreach ($taxonomyUpdates as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)
                    ->where($column, 'App\\Models\\HikingRoute')
                    ->update([$column => 'App\\Models\\EcTrack']);
            }
        }
    }
};
