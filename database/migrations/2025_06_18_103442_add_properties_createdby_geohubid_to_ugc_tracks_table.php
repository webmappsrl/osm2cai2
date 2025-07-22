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
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->jsonb('properties')->nullable()->after('raw_data');
            $table->string('created_by', 20)->nullable()->after('app_id');
            $table->integer('geohub_app_id')->nullable()->after('created_by');
        });

        $main_keys = [
            'name', 'type', 'uuid', 'media', 'app_id', 'device', 'photos',
            'createdAt', 'nominatim', 'updatedAt', 'description', 'photoKeys', 'position', 'storedPhotoKeys', 'displayPosition', 'sync_id','locations'
        ];

        // Lista delle chiavi che rappresentano dati del FORM utente (non metadati tecnici)
        $all_form_keys = [
            'activity',
            'ancillary_works',
            'cai_scale',
            'cmt',
            'date',
            'desc',
            'description',
            'difficulty',
            'distance',
            'distance_comp',
            'duration',
            'duration_backward',
            'duration_forward',
            'elevation_gain',
            'feature_image',
            'from',
            'id',
            'index',
            'issue_description',
            'issue_status',
            'key',
            'layers',
            'links',
            'note',
            'oa_address',
            'oa_locType',
            'orizontal_sign',
            'out_source_feature_id',
            'ref',
            'roundtrip',
            'searchable',
            'taxonomy',
            'time',
            'title',
            'to',
            'trail_building',
            'trimmer',
            'type',
            'vertical_sign',
            'via_ferrata',
            'visibility',
        ];

        DB::table('ugc_tracks')->select('id', 'geohub_id', 'raw_data')->orderBy('id')->chunk(100, function ($tracks) use ($all_form_keys, $main_keys) {
            foreach ($tracks as $track) {
                if (empty($track->raw_data)) {
                    continue;
                }
                $raw = json_decode($track->raw_data, true);
                if (! is_array($raw)) {
                    continue;
                }
                $properties = [];
                $properties['id'] = $track->geohub_id;
                // Solo se valorizzati

                foreach ($main_keys as $key) {
                    if (isset($raw[$key]) && $raw[$key] !== null && $raw[$key] !== '') {
                        $properties[$key] = $raw[$key];
                    }
                }
                // form solo con chiavi valorizzate
                $form = [];
                // Aggiungi sempre id se disponibile
                $form['id'] = $raw['id'] ?? null;

                foreach ($all_form_keys as $key) {
                    if (isset($raw[$key]) && $raw[$key] !== null && $raw[$key] !== '') {
                        $form[$key] = $raw[$key];
                    }
                }
                $properties['form'] = $form;
                DB::table('ugc_tracks')->where('id', $track->id)->update([
                    'properties' => json_encode($properties),
                ]);
            }
        });

        // Popola la colonna geohub_app_id
        // 1. Se app_id è geohub_XX prendi XX
        DB::table('ugc_tracks')->select('id', 'app_id')->where('app_id', 'like', 'geohub\_%')->orderBy('id')->chunk(100, function ($tracks) {
            foreach ($tracks as $track) {
                if (preg_match('/^geohub_(\d+)$/', $track->app_id, $matches)) {
                    DB::table('ugc_tracks')->where('id', $track->id)->update(['geohub_app_id' => (int) $matches[1]]);
                }
            }
        });

        // 2. Se app_id è osm2cai, assegna sempre app_id 26 (come per i POI)
        DB::table('ugc_tracks')->where('app_id', 'osm2cai')->update(['geohub_app_id' => 26]);

        // Popola la colonna created_by SOLO con 'platform' e 'device' in base al valore ORIGINALE di app_id
        DB::table('ugc_tracks')->where('app_id', 'osm2cai')->update(['created_by' => 'platform']);
        DB::table('ugc_tracks')->where('app_id', '!=', 'osm2cai')->update(['created_by' => 'device']);

        // Aggiorna app_id in base a geohub_app_id SOLO ALLA FINE
        DB::table('ugc_tracks')->where('geohub_app_id', 26)->update(['app_id' => 1]);
        DB::table('ugc_tracks')->where('geohub_app_id', 20)->update(['app_id' => 2]);
        DB::table('ugc_tracks')->where('geohub_app_id', 58)->update(['app_id' => 3]);
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->dropColumn('properties');
            $table->dropColumn('created_by');
            $table->dropColumn('geohub_app_id');
        });
    }
}; 