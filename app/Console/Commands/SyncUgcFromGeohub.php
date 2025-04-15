<?php

namespace App\Console\Commands;

use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use App\Services\GeometryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncUgcFromGeohub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:import-ugc-from-geohub {--app= : The app id to sync (20,26,58)} {--type= : The type to sync (poi,track,media)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'this command syncs ugc from geohub to osm2cai db using geohub api';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $baseApiUrl = 'https://geohub.webmapp.it/api/ugc/';

    private $apps = [
        20 => 'it.webmapp.sicai',
        26 => 'it.webmapp.osm2cai',
        58 => 'it.webmapp.acquasorgente',
    ];

    private $types = ['poi', 'track', 'media'];

    private $createdElements = [
        'poi' => 0,
        'track' => 0,
        'media' => 0,
    ];

    private $updatedElements = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $appId = $this->option('app');
        $type = $this->option('type');

        if ($appId) {
            $this->syncApp($appId, $type);
        } else {
            $this->syncAllApps($type);
        }

        Log::channel('import-ugc')->info('Sync completato.');
        $this->info('Sync completato.');

        return ['createdElements' => $this->createdElements, 'updatedElements' => $this->updatedElements];
    }

    private function syncAllApps($type = null)
    {
        foreach ($this->apps as $appId => $appName) {
            $this->syncApp($appId, $type);
        }
    }

    private function syncApp($appId, $type = null)
    {
        Log::channel('import-ugc')->info("Avvio sync per l'app con ID $appId");
        $this->info("Avvio sync per l'app con ID $appId");

        if (! in_array($appId, array_keys($this->apps))) {
            Log::channel('import-ugc')->error("ID app non valido: $appId");
            $this->error("ID app non valido: $appId");

            return;
        }

        $typesToSync = $type ? [$type] : $this->types;

        if ($type && !in_array($type, $this->types)) {
            Log::channel('import-ugc')->error("Tipo non valido: $type");
            $this->error("Tipo non valido: $type");
            return;
        }

        foreach ($typesToSync as $currentType) {
            $endpoint = "{$this->baseApiUrl}{$currentType}/geojson/{$appId}/list";
            $this->syncType($currentType, $endpoint, $appId);
        }
    }

    private function syncType($type, $endpoint, $appId)
    {
        Log::channel('import-ugc')->info("Effettuando il sync per $type da $endpoint");
        $list = json_decode($this->get_content($endpoint), true);
        if (empty($list)) {
            Log::channel('import-ugc')->info("Nessun elemento da sincronizzare per $type da $endpoint");
            $this->info("Nessun elemento da sincronizzare per $type da $endpoint");

            return;
        }

        foreach ($list as $id => $updated_at) {
            $this->syncElement($type, $id, $updated_at, $appId);
        }
    }

    private function get_content($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        $data = curl_exec($ch);
        if ($data === false) {
            Log::channel('import-ugc')->error("Failed to fetch content from URL: $url");
            $this->error("Failed to fetch content from URL: $url");
            throw new \Exception("Failed to fetch content from URL: $url");
        }
        curl_close($ch);

        return $data;
    }

    private function syncElement($type, $id, $updated_at, $appId)
    {
        Log::channel('import-ugc')->info("Controllo $type con geohub id $id");
        $this->info("Controllo $type con geohub id $id");
        $model = $this->getModel($type, $id);
        $geoJson = $this->getGeojson("https://geohub.webmapp.it/api/ugc/{$type}/geojson/{$id}/osm2cai");

        $needsUpdate = $model->wasRecentlyCreated ||
            $model->updated_at < $updated_at;

        if ($needsUpdate) {
            $this->syncRecord($model, $geoJson, $id, $appId, $type);
            if ($model->wasRecentlyCreated) {
                $this->createdElements[$type]++;
                $this->info("Creato nuovo $type con id $id");
                Log::channel('import-ugc')->info("Creato nuovo $type con id $id");
            } else {
                $this->updatedElements[] = ucfirst($type) . ' with id ' . $id . ' updated';
                $this->info("Aggiornato $type con geohub id $id");
                Log::channel('import-ugc')->info("Aggiornato $type con geohub id $id");
            }
        }
    }

    private function getModel($type, $id)
    {
        $model = 'App\Models\Ugc' . ucfirst($type);

        return $model::firstOrCreate(['geohub_id' => $id]);
    }

    private function getGeojson($url)
    {
        $geoJson = json_decode($this->get_content($url), true);
        if ($geoJson === null) {
            Log::channel('import-ugc')->error("Errore nel fetch del GeoJSON da $url");
            $this->error("Errore nel fetch del GeoJSON da $url");
            throw new \Exception("Errore nel fetch del GeoJSON da $url");
        }

        return $geoJson;
    }

    private function syncRecord($model, $geoJson, $id, $appId, $type)
    {
        Log::channel('import-ugc')->info("Aggiornamento $type con id $id");
        $this->info("Aggiornamento $type con id $id");

        $rawData = $this->getRawData($geoJson);

        $data = [
            'name' => $geoJson['properties']['name'] ?? null,
            'raw_data' => $rawData,
            'updated_at' => $geoJson['properties']['updated_at'] ?? null,
            'taxonomy_wheres' => $geoJson['properties']['taxonomy_wheres'] ?? null,
            'app_id' => 'geohub_' . $appId,
        ];

        $user = User::where('email', $geoJson['properties']['user_email'])->first();

        if (isset($geoJson['geometry']) && $geoJson['geometry'] != null) {
            //if the model is an ugc track, force the geometry to be a MultiLineStringZ
            if ($model instanceof UgcTrack) {
                $data['geometry'] = GeometryService::getService()->geojsonToGeometry($geoJson['geometry']);
            } else {
                $data['geometry'] = DB::raw('ST_Transform(ST_GeomFromGeoJSON(\'' . json_encode($geoJson['geometry']) . '\'), 4326)');
            }
        }

        if ($model instanceof UgcPoi) {
            $data['form_id'] = $rawData['id'] ?? null;
        }


        if ($user) {
            $model->user_id = $user->id;
        } else {
            Log::channel('import-ugc')->info('Utente con email ' . $geoJson['properties']['user_email'] . ' non trovato');
        }
        if ($model instanceof UgcMedia) {
            // Set media URL from geojson properties
            $data['relative_url'] = $geoJson['properties']['url'] ?? null;

            // Extract related POIs and tracks IDs
            $poisGeohubIds = $geoJson['properties']['ugc_pois'] ?? [];
            $tracksGeohubIds = $geoJson['properties']['ugc_tracks'] ?? [];

            // Skip syncing if media has no associated POIs or tracks
            if (empty($poisGeohubIds) && empty($tracksGeohubIds)) {
                Log::channel('import-ugc')->info('Media skipped: no associated POIs or tracks');
                return;
            }

            // Associate with POI if available
            if (!empty($poisGeohubIds)) {
                $poisIds = UgcPoi::whereIn('geohub_id', $poisGeohubIds)
                    ->pluck('id')
                    ->toArray();
                $model->ugc_poi_id = $poisIds[0] ?? null;
            }

            // Associate with track if available
            if (!empty($tracksGeohubIds)) {
                $tracksIds = UgcTrack::whereIn('geohub_id', $tracksGeohubIds)
                    ->pluck('id')
                    ->toArray();
                $model->ugc_track_id = $tracksIds[0] ?? null;
            }
        }
        $model->fill($data);
        $model->save();
        Log::channel('import-ugc')->info('Aggiornamento completato');
        $this->info('Aggiornamento completato');
    }

    private function getRawData($geoJson)
    {
        $rawData = $geoJson['properties']['raw_data'] ?? null;
        if ($rawData) {
            if (is_string($rawData)) {
                $rawData = json_decode($rawData, true);
            }
        }

        return $rawData;
    }
}
