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
    protected $description = 'Synchronizes user-generated content from Geohub to OSM2CAI database using Geohub API';

    /**
     * Geohub API configuration
     */
    private string $geohubBaseUrl = 'https://geohub.webmapp.it';

    private string $geohubApiUrl = '/api/ugc/';

    /**
     * Supported applications
     */
    private array $apps = [
        20 => 'it.webmapp.sicai',
        26 => 'it.webmapp.osm2cai',
        58 => 'it.webmapp.acquasorgente',
    ];

    /**
     * Supported content types
     */
    private array $types = ['poi', 'track', 'media'];

    /**
     * Sync statistics
     */
    private array $createdElements = [
        'poi' => 0,
        'track' => 0,
        'media' => 0,
    ];

    private array $updatedElements = [];

    /**
     * Logger channel name
     */
    private const LOG_CHANNEL = 'import-ugc';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $baseApiUrl = 'http://host.docker.internal:8000/api/ugc/';

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
     * @return array
     */
    public function handle(): array
    {
        $appId = $this->option('app');
        $type = $this->option('type');

        if ($appId) {
            $this->syncApp($appId, $type);
        } else {
            $this->syncAllApps($type);
        }

        $this->logInfo('Sync completato.');

        return [
            'createdElements' => $this->createdElements,
            'updatedElements' => $this->updatedElements,
        ];
    }

    /**
     * Sync all configured applications
     */
    private function syncAllApps(?string $type = null): void
    {
        foreach ($this->apps as $appId => $appName) {
            $this->syncApp($appId, $type);
        }
    }

    /**
     * Sync a specific application
     */
    private function syncApp($appId, ?string $type = null): void
    {
        $this->logInfo("Avvio sync per l'app con ID $appId");

        if (! $this->validateAppId($appId)) {
            return;
        }

        $typesToSync = $type ? [$type] : $this->types;

        if ($type && ! in_array($type, $this->types)) {
            Log::channel('import-ugc')->error("Tipo non valido: $type");
            $this->error("Tipo non valido: $type");

            return;
        }

        foreach ($typesToSync as $currentType) {
            $endpoint = "{$this->baseApiUrl}{$currentType}/geojson/{$appId}/list";
            $this->syncType($currentType, $endpoint, $appId);
        }
    }

    /**
     * Validate if the app ID is supported
     */
    private function validateAppId($appId): bool
    {
        if (! array_key_exists($appId, $this->apps)) {
            $this->logError("ID app non valido: $appId");

            return false;
        }

        return true;
    }

    /**
     * Get types to sync based on input
     */
    private function getTypesToSync(?string $type): ?array
    {
        if (! $type) {
            return $this->types;
        }

        if (! in_array($type, $this->types)) {
            $this->logError("Tipo non valido: $type");

            return null;
        }

        return [$type];
    }

    /**
     * Build the API endpoint URL
     */
    private function buildEndpointUrl(string $type, $appId): string
    {
        return "{$this->geohubBaseUrl}{$this->geohubApiUrl}{$type}/geojson/{$appId}/list";
    }

    /**
     * Sync all elements of a specific type
     */
    private function syncType(string $type, string $endpoint, $appId): void
    {
        $this->logInfo("Effettuando il sync per $type da $endpoint");

        $list = $this->fetchContentList($endpoint);
        if (empty($list)) {
            $this->logInfo("Nessun elemento da sincronizzare per $type da $endpoint");

            return;
        }

        foreach ($list as $id => $updated_at) {
            $this->syncElement($type, $id, $updated_at, $appId);
        }
    }

    /**
     * Fetch content list from API
     */
    private function fetchContentList(string $endpoint): array
    {
        $content = $this->getContent($endpoint);

        return json_decode($content, true) ?? [];
    }

    /**
     * Get content from a URL with error handling
     */
    private function getContent(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        $data = curl_exec($ch);
        if ($data === false) {
            $errorMessage = "Failed to fetch content from URL: $url";
            $this->logError($errorMessage);
            curl_close($ch);
            throw new \Exception($errorMessage);
        }

        curl_close($ch);

        return $data;
    }

    /**
     * Sync a single element
     */
    private function syncElement(string $type, $id, $updated_at, $appId): void
    {
        $this->logInfo("Controllo $type con geohub id $id");

        $model = $this->getModel($type, $id);
        $geoJson = $this->getGeojson("http://host.docker.internal:8000/api/ugc/{$type}/geojson/{$id}/osm2cai");

        if (! $this->needsUpdate($model, $updated_at)) {
            return;
        }

        $this->syncRecord($model, $geoJson, $id, $appId, $type);
        $this->updateSyncStatistics($model, $type, $id);
    }

    /**
     * Build URL for a specific element
     */
    private function buildElementUrl(string $type, $id): string
    {
        return "{$this->geohubBaseUrl}{$this->geohubApiUrl}{$type}/geojson/{$id}/osm2cai";
    }

    /**
     * Check if model needs to be updated
     */
    private function needsUpdate($model, $updated_at): bool
    {
        return $model->wasRecentlyCreated || $model->updated_at < $updated_at;
    }

    /**
     * Update sync statistics after processing an element
     */
    private function updateSyncStatistics($model, string $type, $id): void
    {
        if (! $model->wasRecentlyDeleted) {
            if ($model->wasRecentlyCreated) {
                $this->createdElements[$type]++;
            } else {
                $this->updatedElements[] = ucfirst($type) . ' with id ' . $id . ' updated';
                $this->info("Aggiornato $type con geohub id $id");
                Log::channel('import-ugc')->info("Aggiornato $type con geohub id $id");
            }
        }
    }

    /**
     * Get or create model for a specific element
     */
    private function getModel(string $type, $id)
    {
        $model = 'App\Models\Ugc' . ucfirst($type);

        return $modelClass::firstOrCreate(['geohub_id' => $id]);
    }

    /**
     * Get GeoJSON data for an element
     */
    private function getGeojson(string $url): array
    {
        $content = $this->getContent($url);
        $geoJson = json_decode($content, true);

        if ($geoJson === null) {
            $errorMessage = "Errore nel fetch del GeoJSON da $url";
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        return $geoJson;
    }

    /**
     * Sync record data from GeoJSON to model
     */
    private function syncRecord($model, array $geoJson, $id, $appId, string $type): void
    {
        $this->logInfo("Aggiornamento $type con id $id");

        $rawData = $this->getRawData($geoJson);

        $data = [
            'name' => $geoJson['properties']['name'] ?? null,
            'raw_data' => $rawData,
            'updated_at' => $geoJson['properties']['updated_at'] ?? null,
            'taxonomy_wheres' => $geoJson['properties']['taxonomy_wheres'] ?? null,
            'app_id' => 'geohub_' . $appId,
        ];

        $this->processGeometry($model, $geoJson, $data);
        $this->processModelSpecificData($model, $rawData, $geoJson);

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

        $model->fill($data);
        $model->save();
        $this->logInfo('Aggiornamento completato');
    }

    /**
     * Prepare base data for all model types
     */
    private function prepareBaseData(array $geoJson, ?array $rawData, $appId): array
    {
        return [
            'name' => $geoJson['properties']['name'] ?? null,
            'raw_data' => $rawData,
            'updated_at' => $geoJson['properties']['updated_at'] ?? null,
            'taxonomy_wheres' => $geoJson['properties']['taxonomy_wheres'] ?? null,
            'app_id' => 'geohub_' . $appId,
        ];
    }

    /**
     * Process geometry data
     */
    private function processGeometry($model, array $geoJson, array &$data): void
    {
        if (! isset($geoJson['geometry']) || $geoJson['geometry'] == null) {
            return;
        }

        if ($model instanceof UgcTrack) {
            $data['geometry'] = GeometryService::getService()->geojsonToGeometry($geoJson['geometry']);
        } else {
            $data['geometry'] = DB::raw('ST_Transform(ST_GeomFromGeoJSON(\'' . json_encode($geoJson['geometry']) . '\'), 4326)');
        }
    }

    /**
     * Process model-specific data
     */
    private function processModelSpecificData($model, ?array $rawData, array $geoJson): void
    {
        // Set user if available
        $user = User::where('email', $geoJson['properties']['user_email'] ?? '')->first();

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
            if (! empty($poisGeohubIds)) {
                $poisIds = UgcPoi::whereIn('geohub_id', $poisGeohubIds)
                    ->pluck('id')
                    ->toArray();
                $model->ugc_poi_id = $poisIds[0] ?? null;
            }

            // Associate with track if available
            if (! empty($tracksGeohubIds)) {
                $tracksIds = UgcTrack::whereIn('geohub_id', $tracksGeohubIds)
                    ->pluck('id')
                    ->toArray();
                $model->ugc_track_id = $tracksIds[0] ?? null;
            }
        }
    }

    /**
     * Process media-specific data
     *
     * @return bool Whether to continue processing the media
     */
    private function processMediaData(UgcMedia $model, array $geoJson, array &$data): bool
    {
        // Set media URL
        $relativeUrl = $geoJson['properties']['relative_url'] ?? null;
        if (! $relativeUrl) {
            $this->logInfo('Media skipped: no relative URL');
            $model->delete();

            return false;
        }
        $data['relative_url'] = $this->geohubBaseUrl . '/storage/' . $relativeUrl;

        // Extract related IDs
        $poisGeohubIds = $geoJson['properties']['ugc_pois'] ?? [];
        $tracksGeohubIds = $geoJson['properties']['ugc_tracks'] ?? [];

        // Skip if no associations
        if (empty($poisGeohubIds) && empty($tracksGeohubIds)) {
            $this->logInfo('Media skipped: no associated POIs or tracks');
            $model->delete();

            return false;
        }

        $this->associateMediaWithPois($model, $poisGeohubIds);
        $this->associateMediaWithTracks($model, $tracksGeohubIds);

        return true;
    }

    /**
     * Associate media with POIs
     */
    private function associateMediaWithPois(UgcMedia $model, array $poisGeohubIds): void
    {
        if (empty($poisGeohubIds)) {
            return;
        }

        $poisIds = UgcPoi::whereIn('geohub_id', $poisGeohubIds)
            ->pluck('id')
            ->toArray();

        $model->ugc_poi_id = $poisIds[0] ?? null;
    }

    /**
     * Associate media with tracks
     */
    private function associateMediaWithTracks(UgcMedia $model, array $tracksGeohubIds): void
    {
        if (empty($tracksGeohubIds)) {
            return;
        }

        $tracksIds = UgcTrack::whereIn('geohub_id', $tracksGeohubIds)
            ->pluck('id')
            ->toArray();

        $model->ugc_track_id = $tracksIds[0] ?? null;
    }

    /**
     * Extract raw data from GeoJSON
     */
    private function getRawData(array $geoJson): ?array
    {
        $rawData = $geoJson['properties']['raw_data'] ?? null;
        if ($rawData && is_string($rawData)) {
            $rawData = json_decode($rawData, true);
        }

        return $rawData;
    }

    /**
     * Log info message to both console and log file
     */
    private function logInfo(string $message): void
    {
        Log::channel(self::LOG_CHANNEL)->info($message);
        $this->info($message);
    }

    /**
     * Log error message to both console and log file
     */
    private function logError(string $message): void
    {
        Log::channel(self::LOG_CHANNEL)->error($message);
        $this->error($message);
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
