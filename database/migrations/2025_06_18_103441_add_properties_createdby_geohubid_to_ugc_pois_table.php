<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('ugc_pois', function (Blueprint $table) {
            $table->jsonb('properties')->nullable()->after('raw_data');
            $table->string('created_by', 20)->nullable()->after('app_id');
            $table->integer('geohub_app_id')->nullable()->after('created_by');
        });

        $main_keys = [
            'name', 'type', 'uuid', 'media', 'app_id', 'device', 'photos',
            'createdAt', 'nominatim', 'updatedAt', 'description', 'photoKeys', 'position', 'storedPhotoKeys', 'displayPosition', 'sync_id',
        ];

        // Lista di tutte le possibili chiavi che possono andare in form
        $all_form_keys = [
            'active',
            'area_type',
            'artifact_type',
            'city',
            'condition',
            'conductivity',
            'conservation_status',
            'Date',
            'date',
            'description',
            'id',
            'index',
            'info',
            'informational_supports',
            'ispra_geosite',
            'location',
            'notes',
            'paths_poi_type',
            'range_time',
            'range_volume',
            'report_type',
            'site_type',
            'temperature',
            'title',
            'vulnerability',
            'vulnerability_reason',
            'waypointtype',
            'Note',
            'snow_coverage',
            'snow_height',
            'snow_weight',
            'snow_weight_01',
            'snow_weight_02',
            'snow_weight_03',
            'snow_weight_04',
            'snow_weight_05',
            'snow_weight_06',
            'snow_weight_07',
            'snow_weight_08',
            'snow_weight_09',
            'snow_weight_10',
            'temperature',
            'waypoint_type',
            'weather',
        ];

        DB::table('ugc_pois')->select('id', 'geohub_id', 'raw_data')->orderBy('id')->chunk(100, function ($pois) use ($all_form_keys, $main_keys) {
            foreach ($pois as $poi) {
                if (empty($poi->raw_data)) {
                    continue;
                }
                $raw = json_decode($poi->raw_data, true);
                if (! is_array($raw)) {
                    continue;
                }
                $properties = [];
                $properties['id'] = $poi->geohub_id;
                // Solo se valorizzati

                foreach ($main_keys as $key) {
                    if (isset($raw[$key]) && $raw[$key] !== null && $raw[$key] !== '') {
                        $properties[$key] = $raw[$key];
                    }
                }
                // form solo con chiavi valorizzate
                $form = [];
                // Aggiungi sempre id
                $form['id'] = isset($raw['id']) ? $raw['id'] : null;
                
                foreach ($all_form_keys as $key) {
                    if (isset($raw[$key]) && $raw[$key] !== null && $raw[$key] !== '') {
                        $form[$key] = $raw[$key];
                    }
                }
                $properties['form'] = $form;
                DB::table('ugc_pois')->where('id', $poi->id)->update([
                    'properties' => json_encode($properties),
                ]);
            }
        });

        // Popola la colonna geohub_app_id
        // 1. Se app_id è geohub_XX prendi XX
        DB::table('ugc_pois')->select('id', 'app_id')->where('app_id', 'like', 'geohub\_%')->orderBy('id')->chunk(100, function ($pois) {
            foreach ($pois as $poi) {
                if (preg_match('/^geohub_(\d+)$/', $poi->app_id, $matches)) {
                    DB::table('ugc_pois')->where('id', $poi->id)->update(['geohub_app_id' => (int) $matches[1]]);
                }
            }
        });
        // 2. Se app_id è osm2cai, controlla form_id
        $form_map = [
            'archaeological_area' => 26,
            'geological_site' => 26,
            'signs' => 26,
            'vertical_sign' => 26,
            'archaeological_site' => 26,
            'report' => 26,
            'water' => 58,
            'poi' => 26,
            'paths' => 26,
        ];
        DB::table('ugc_pois')->select('id', 'form_id')->where('app_id', 'osm2cai')->orderBy('id')->chunk(100, function ($pois) use ($form_map) {
            foreach ($pois as $poi) {
                if (isset($form_map[$poi->form_id])) {
                    DB::table('ugc_pois')->where('id', $poi->id)->update(['geohub_app_id' => $form_map[$poi->form_id]]);
                }
            }
        });

        // Popola la colonna created_by SOLO con 'platform' e 'device' in base al valore ORIGINALE di app_id
        DB::table('ugc_pois')->where('app_id', 'osm2cai')->update(['created_by' => 'platform']);
        DB::table('ugc_pois')->where('app_id', '!=', 'osm2cai')->update(['created_by' => 'device']);

        // Aggiorna app_id in base a geohub_app_id SOLO ALLA FINE
        DB::table('ugc_pois')->where('geohub_app_id', 26)->update(['app_id' => 1]);
        DB::table('ugc_pois')->where('geohub_app_id', 20)->update(['app_id' => 2]);
        DB::table('ugc_pois')->where('geohub_app_id', 58)->update(['app_id' => 3]);
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('ugc_pois', function (Blueprint $table) {
            $table->dropColumn('properties');
            $table->dropColumn('created_by');
            $table->dropColumn('geohub_app_id');
        });
    }
};
