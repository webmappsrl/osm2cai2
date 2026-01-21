<?php

namespace App\Models;

use App\Jobs\CalculateIntersectionsJob;
use App\Jobs\CheckNearbyEcPoisJob;
use App\Jobs\CheckNearbyHutsJob;
use App\Jobs\CheckNearbyNaturalSpringsJob;
use App\Observers\HikingRouteObserver;
use App\Services\HikingRouteDescriptionService;
use App\Traits\AwsCacheable;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\TagsMappingTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Wm\WmPackage\Models\EcTrack;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMapTrait;

class HikingRoute extends EcTrack
{
    use AwsCacheable;
    use FeatureCollectionMapTrait;
    use HasFactory;
    use OsmfeaturesGeometryUpdateTrait;
    use OsmfeaturesSyncableTrait;
    use SpatialDataTrait;
    use TagsMappingTrait;

    // Costanti per lo stile dei punti (pali) sulla mappa
    protected const POINT_STROKE_COLOR = 'rgb(255, 255, 255)';

    protected const POINT_STROKE_WIDTH = 2;

    protected const POINT_FILL_COLOR = 'rgba(255, 0, 0, 0.8)';

    protected const CHECKPOINT_FILL_COLOR = 'rgb(255, 160, 0)';

    protected const POINT_RADIUS = 4;

    protected const CHECKPOINT_RADIUS = 6;

    protected $table = 'hiking_routes';

    protected $fillable = [
        'name',
        'app_id',
        'user_id',
        'osmid',
        'geometry',
        'osm2cai_status',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
        'tdh',
        'region_favorite',
        'feature_image',
        'validator_id',
        'validation_date',
        'issues_status',
        'issues_last_update',
        'issues_chronology',
        'issues_user_id',
        'issues_description',
        'description_cai_it',
        'geometry_raw_data',
        'properties',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'array',
        'issues_last_update' => 'date',
        'tdh' => 'array',
        'issues_chronology' => 'array',
        'properties' => 'array',
    ];

    private HikingRouteDescriptionService $descriptionService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->descriptionService = app(HikingRouteDescriptionService::class);
    }

    public function getLayerRelationName(): string
    {
        return 'ecTracks';
    }

    protected static function booted()
    {
        parent::booted();
        self::observe(HikingRouteObserver::class);
    }

    /**
     * Get the index name for Elasticsearch
     */
    public function searchableAs(): string
    {
        return 'hiking_routes';
    }

    /**
     * Determine if the model should be searchable.
     * Only index records with valid geometry and osm2cai_status can be null or != 0
     */
    public function shouldBeSearchable()
    {
        return ! is_null($this->geometry) && ($this->osm2cai_status === null || $this->osm2cai_status != 0);
    }

    /**
     * Override toSearchableArray to handle null geometry gracefully
     */
    public function toSearchableArray()
    {
        // Skip indexing if geometry is null
        if (! $this->geometry) {
            return [];
        }

        // Call parent method if geometry is valid
        return parent::toSearchableArray();
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('feature_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg']);
    }

    /**
     * Getter for the ref field
     */
    public function getRefReiAttribute(): string
    {
        if (isset($this->osmfeatures_data['properties']['ref_REI'])) {
            return $this->osmfeatures_data['properties']['ref_REI'];
        }

        // else compute it
        return $this->getRefReiCompAttribute();
    }

    /**
     * Getter for the distance_comp field in km
     */
    public function getDistanceCompAttribute(): ?float
    {
        if (! $this->geometry) {
            return null;
        }

        $result = DB::selectOne(
            'SELECT ST_Length(geometry, true) AS distance FROM hiking_routes WHERE id = ?',
            [$this->id]
        );

        return round($result ? $result->distance / 1000 : null, 2);
    }

    /**
     * Getter for the geometry_sync field
     *
     * Converts the local geometry to GeoJSON format and compares it with the
     * geometry stored in osmfeatures_data to determine if they are in sync
     *
     * @return bool True if geometries match, false otherwise
     */
    public function getGeometrySyncAttribute(): bool
    {
        // Usa un attributo cache per evitare query duplicate quando viene chiamato più volte
        $cacheKey = '_geometry_sync_cached';
        if (isset($this->attributes[$cacheKey])) {
            return $this->attributes[$cacheKey];
        }

        $geojson = $this->query()->where('id', $this->id)->selectRaw('ST_AsGeoJSON(geometry) as geom')->get()->pluck('geom')->first();
        $geom = json_decode($geojson, true);

        $osmfeaturesGeom = $this->osmfeatures_data['geometry'] ?? null;

        // Normalizza le geometrie rimuovendo la dimensione Z per il confronto
        // La geometria nel DB ha sempre Z=0 aggiunta da ST_Force3DZ, mentre quella in osmfeatures_data potrebbe non averla
        $normalizedDbGeom = $this->normalizeGeometry($geom);
        $normalizedOsmfeaturesGeom = $osmfeaturesGeom ? $this->normalizeGeometry($osmfeaturesGeom) : null;

        // Confronta usando JSON per un confronto robusto di array annidati complessi
        // Gestisce correttamente il caso in cui una delle due geometrie è null
        if ($normalizedDbGeom === null && $normalizedOsmfeaturesGeom === null) {
            $isSync = true;
        } elseif ($normalizedDbGeom === null || $normalizedOsmfeaturesGeom === null) {
            $isSync = false;
        } else {
            // Confronta le geometrie normalizzate usando JSON per un confronto deterministico
            $isSync = json_encode($normalizedDbGeom, JSON_UNESCAPED_SLASHES) === json_encode($normalizedOsmfeaturesGeom, JSON_UNESCAPED_SLASHES);
        }

        // Cache del risultato per questa istanza
        $this->attributes[$cacheKey] = $isSync;

        return $isSync;
    }

    /**
     * Normalizza una geometria rimuovendo la dimensione Z (se presente)
     * per permettere il confronto tra geometrie con e senza Z
     */
    private function normalizeGeometry($geometry): ?array
    {
        if (! is_array($geometry)) {
            return $geometry;
        }

        if (isset($geometry['coordinates'])) {
            $geometry['coordinates'] = $this->removeZDimension($geometry['coordinates']);
        }

        return $geometry;
    }

    /**
     * Rimuove la dimensione Z dalle coordinate (terzo elemento di ogni punto)
     */
    private function removeZDimension($coordinates): array
    {
        if (! is_array($coordinates)) {
            return $coordinates;
        }

        // Se è un punto [x, y, z] o [x, y], rimuovi Z
        if (is_numeric($coordinates[0] ?? null)) {
            return array_slice($coordinates, 0, 2);
        }

        // Altrimenti ricorri sugli array annidati
        return array_map(function ($item) {
            return $this->removeZDimension($item);
        }, $coordinates);
    }

    /**
     * Returns the OSMFeatures API endpoint for listing features for the model.
     */
    public static function getOsmfeaturesEndpoint(): string
    {
        return 'https://osmfeatures.maphub.it/api/v1/features/hiking-routes/';
    }

    /**
     * Returns the query parameters for listing features for the model.
     *
     * The array keys should be the query parameter name and the values
     * should be the expected value.
     *
     * @return array<string,string>
     */
    public static function getOsmfeaturesListQueryParameters(): array
    {
        return ['status' => 1]; // get only hiking routes with osm2cai status greater than 0 (current values in osmfeatures: 1,2,3)
    }

    /**
     * Update the local database after a successful OSMFeatures sync.
     */
    public static function osmfeaturesUpdateLocalAfterSync(string $osmfeaturesId): void
    {
        $model = self::where('osmfeatures_id', $osmfeaturesId)->first();
        if (! $model) {
            throw WmOsmfeaturesException::modelNotFound($osmfeaturesId);
        }

        $osmfeaturesData = is_string($model->osmfeatures_data) ? json_decode($model->osmfeatures_data, true) : $model->osmfeatures_data;

        if (! $osmfeaturesData) {
            Log::channel('wm-osmfeatures')->info('No data found for HikingRoute ' . $osmfeaturesId);

            return;
        }

        // Check if the osm2cai_status from osmfeatures is 0
        if (isset($osmfeaturesData['properties']['osm2cai_status'])) {
            $incomingStatus = $osmfeaturesData['properties']['osm2cai_status'];
            if ($incomingStatus == 0) {
                Log::channel('wm-osmfeatures')->info('HikingRoute ' . $osmfeaturesId . ' has incoming osm2cai_status 0. Deleting model.');
                $model->delete(); // This will trigger the 'deleting' event

                return; // Stop further processing for this model
            }
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        if ($model->osm2cai_status != 4) {
            // Update osm2cai_status if necessary
            if (isset($osmfeaturesData['properties']['osm2cai_status']) && $osmfeaturesData['properties']['osm2cai_status'] !== null) {
                if ($model->osm2cai_status !== $osmfeaturesData['properties']['osm2cai_status']) {
                    $updateData['osm2cai_status'] = $osmfeaturesData['properties']['osm2cai_status'];
                    Log::channel('wm-osmfeatures')->info('osm2cai_status updated for HikingRoute ' . $osmfeaturesId);
                }
            }
        }

        // Execute the update only if there are data to update
        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    /**
     * Get Data for nova Link Card
     *
     * @return array
     */
    public function getDataForNovaLinksCard()
    {
        if (is_string($this->osmfeatures_data)) {
            $osmId = json_decode($this->osmfeatures_data, true)['properties']['osm_id'];
        } else {
            $osmId = $this->osmfeatures_data['properties']['osm_id'];
        }
        $infomontLink = 'https://15.app.geohub.webmapp.it/#/map';
        $osm2caiLink = 'https://26.app.geohub.webmapp.it/#/map';
        $osmLink = 'https://www.openstreetmap.org/relation/' . $osmId;
        $wmt = 'https://hiking.waymarkedtrails.org/#route?id=' . $osmId;
        $analyzer = 'https://ra.osmsurround.org/analyzeRelation?relationId=' . $osmId . '&noCache=true&_noCache=on';
        $endpoint = 'https://geohub.webmapp.it/api/osf/track/osm2cai/';
        $api = $endpoint . $this->id;

        $headers = get_headers($api);
        $statusLine = $headers[0];

        if (strpos($statusLine, '200 OK') !== false) {
            // The API returned a success response
            $data = json_decode(file_get_contents($api), true);
            if (! empty($data)) {
                if ($data['properties']['id'] !== null) {
                    $infomontLink .= '?track=' . $data['properties']['id'];
                    $osm2caiLink .= '?track=' . $data['properties']['id'];
                }
            }
        }

        return [
            'id' => $this->id,
            'osm_id' => $osmId,
            'infomontLink' => $infomontLink,
            'osm2caiLink' => $osm2caiLink,
            'openstreetmapLink' => $osmLink,
            'waymarkedtrailsLink' => $wmt,
            'analyzerLink' => $analyzer,
        ];
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class);
    }

    public function provinces()
    {
        return $this->belongsToMany(Province::class);
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'hiking_route_club');
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_hiking_route');
    }

    public function sectors()
    {
        return $this->belongsToMany(Sector::class)->withPivot(['percentage']);
    }

    public function issueUser()
    {
        return $this->belongsTo(User::class, 'issues_user_id', 'id');
    }

    public function itineraries()
    {
        return $this->belongsToMany(Itinerary::class);
    }

    public function nearbyCaiHuts()
    {
        return $this->belongsToMany(CaiHut::class, 'hiking_route_cai_hut')->withPivot(['buffer']);
    }

    public function nearbyNaturalSprings()
    {
        return $this->belongsToMany(NaturalSpring::class, 'hiking_route_natural_spring')->withPivot(['buffer']);
    }

    public function nearbyEcPois()
    {
        return $this->belongsToMany(EcPoi::class, 'hiking_route_ec_poi')->withPivot(['buffer']);
    }

    /**
     * Override della relazione ecPois del parent EcTrack per usare la tabella corretta
     * La tabella pivot per HikingRoute è 'hiking_route_ec_poi' non 'ec_poi_hiking_route'
     */
    public function ecPois(): BelongsToMany
    {
        return $this->belongsToMany(EcPoi::class, 'hiking_route_ec_poi')->withPivot('order')->orderByPivot('order');
    }

    public function mountainGroups()
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_group_hiking_route', 'hiking_route_id', 'mountain_group_id');
    }

    public function signageProjects()
    {
        return $this->morphToMany(SignageProject::class, 'signage_projectable')->using(SignageProjectable::class);
    }

    public function getFeatureCollectionMap(): array
    {
        $geojson = $this->getFeatureCollectionMapFromTrait();
        $properties = [
            'strokeColor' => 'blue',
            'strokeWidth' => 4,
            'id' => $this->id,
            'osmfeatures_id' => $this->osmfeatures_id,
        ];
        $geojson['features'][0]['properties'] = $properties;

        // Aggiungi le properties.signage dell'hikingRoute al GeoJSON per renderle disponibili al frontend
        $hikingRouteProperties = $this->properties ?? [];
        if (isset($hikingRouteProperties['signage'])) {
            $geojson['features'][0]['properties']['signage'] = $hikingRouteProperties['signage'];
        }

        $checkpointPoleIds = $hikingRouteProperties['signage']['checkpoint'] ?? [];
        $poleFeatures = $this->getPolesWithBuffer()->map(function ($pole) use ($checkpointPoleIds) {
            $poleFeature = $this->getFeatureMap($pole->geometry);
            $isCheckpoint = in_array($pole->id, $checkpointPoleIds);
            $osmTags = null;
            if ($pole->osmfeatures_data && isset($pole->osmfeatures_data['properties']['osm_tags'])) {
                $osmTags = $pole->osmfeatures_data['properties']['osm_tags'];
            }

            $properties = [
                'id' => $pole->id,
                'name' => $pole->name ?? '',
                'description' => $pole->properties['description'] ?? '',
                'tooltip' => $pole->ref,
                'ref' => $pole->ref,
                'clickAction' => 'popup',
                'link' => url('/resources/poles/' . $pole->id),
                'pointStrokeColor' => self::POINT_STROKE_COLOR,
                'pointStrokeWidth' => self::POINT_STROKE_WIDTH,
                'pointFillColor' => $isCheckpoint ? self::CHECKPOINT_FILL_COLOR : self::POINT_FILL_COLOR,
                'pointRadius' => $isCheckpoint ? self::CHECKPOINT_RADIUS : self::POINT_RADIUS,
                'signage' => $pole->properties['signage'] ?? [],
                'osmTags' => $osmTags,
            ];
            $poleFeature['properties'] = $properties;

            return $poleFeature;
        })->toArray();
        $uncheckedGeometryFeature = $this->getFeatureMap($this->geometry_raw_data);
        $properties = [
            'strokeColor' => 'red',
            'strokeWidth' => 2,
            'id' => $this->id,
            'osmfeatures_id' => $this->osmfeatures_id,
        ];
        if (isset($hikingRouteProperties['signage'])) {
            $properties['signage'] = $hikingRouteProperties['signage'];
        }
        $uncheckedGeometryFeature['properties'] = $properties;

        $geojson['features'] = array_merge($poleFeatures,  $geojson['features'], [$uncheckedGeometryFeature]);

        return $geojson;
    }

    public function cleanRelations()
    {
        $this->regions()->detach();
        $this->provinces()->detach();
        $this->clubs()->detach();
        $this->areas()->detach();
        $this->sectors()->detach();
        $this->itineraries()->detach();
        $this->nearbyCaiHuts()->detach();
        $this->nearbyNaturalSprings()->detach();
        $this->nearbyEcPois()->detach();
        $this->mountainGroups()->detach();
    }

    /**
     * Check if the hiking route has been validated
     *
     * A route is considered validated if it has a validation date set
     *
     * @return bool True if the route is validated, false otherwise
     */
    public function validated(): bool
    {
        if (! empty($this->validation_date)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the hiking route has correct geometry
     *
     * @return bool True if the geometry is correct, false otherwise
     */
    public function hasCorrectGeometry()
    {
        $geojson = $this->query()->where('id', $this->id)->selectRaw('ST_AsGeoJSON(geometry) as geom')->get()->pluck('geom')->first();
        $geom = json_decode($geojson, true);
        if (! $geom) {
            return false;
        }
        $nseg = count($geom['coordinates']);
        if ($nseg > 1 && $this->osm2cai_status == 4) {
            return false;
        }

        return true;
    }

    /**
     * Get a hiking route by its OpenStreetMap ID
     *
     * Looks up a hiking route using the OSM ID stored in the osmfeatures_data properties.
     * Returns the first matching route or null if not found.
     *
     * @param  string  $osmId  The OpenStreetMap ID to search for
     * @return HikingRoute|null The matching hiking route if found, null otherwise
     */
    public static function getHikingRouteByOsmId(string $osmId): ?self
    {
        return self::where('osmfeatures_data->properties->osm_id', $osmId)->first();
    }

    /**
     * Get the main sector associated with this hiking route
     *
     * Returns the sector with the highest percentage coverage of this route.
     * Uses a raw SQL query to find the sector_id with maximum percentage,
     * then looks up the corresponding Sector model.
     *
     * @return Sector|null The main sector if found, null otherwise
     */
    public function mainSector()
    {
        $sectorId = DB::select('
        SELECT sector_id
        FROM hiking_route_sector
        WHERE hiking_route_id = ?
        ORDER BY percentage DESC
        LIMIT 1
    ', [$this->id]);

        if (empty($sectorId)) {
            return null;
        }

        return Sector::find($sectorId[0]->sector_id);
    }

    /**
     * Compute missing fields for TDH API integration
     *
     * Aggregates data from multiple sources:
     * - Start/end point information from ISTAT database
     * - Technical route information from DEM service
     * - CAI scale and description
     * - Route geometry analysis
     *
     * @return array Associative array containing all TDH API fields
     */
    public function computeTdh(): array
    {
        $fromInfo = $this->getFromInfo();
        $toInfo = $this->getToInfo();
        $techInfo = $this->getTechInfoFromDem();

        $tdh = [
            'cai_scale_string' => $this->getCaiScaleString(),
            'cai_scale_description' => $this->getCaiScaleDescription(),
            'from' => $fromInfo['from'],
            'city_from' => $fromInfo['city_from'],
            'city_from_istat' => $fromInfo['city_from_istat'],
            'region_from' => $fromInfo['region_from'],
            'region_from_istat' => $fromInfo['region_from_istat'],
            'to' => $toInfo['to'],
            'city_to' => $toInfo['city_to'],
            'city_to_istat' => $toInfo['city_to_istat'],
            'region_to' => $toInfo['region_to'],
            'region_to_istat' => $toInfo['region_to_istat'],
            'roundtrip' => $this->osmfeatures_data['properties']['roundtrip'],
            'abstract' => $this->getAbstract($fromInfo, $toInfo, $techInfo),
            'distance' => $techInfo['distance'],
            'ascent' => $techInfo['ascent'],
            'descent' => $techInfo['descent'],
            'duration_forward' => $techInfo['duration_forward'],
            'duration_backward' => $techInfo['duration_backward'],
            'ele_from' => $techInfo['ele_from'],
            'ele_to' => $techInfo['ele_to'],
            'ele_max' => $techInfo['ele_max'],
            'ele_min' => $techInfo['ele_min'],
            'gpx_url' => $techInfo['gpx_url'],
        ];

        return $tdh;
    }

    /**
     * Get starting point information from ISTAT municipality database
     *
     * Queries the municipality_boundaries table to find which municipality
     * intersects with the route's starting point. Returns municipality and
     * region information based on ISTAT codes.
     *
     * @return array Associative array containing:
     *               - from: Starting point name
     *               - city_from: Municipality name
     *               - city_from_istat: Municipality ISTAT code
     *               - region_from: Region name
     *               - region_from_istat: Region ISTAT code
     */
    public function getFromInfo(): array
    {
        $from = $this->osmfeatures_data['properties']['from'] ?? null;
        $info = [
            'from' => $from,
            'city_from' => 'Unknown',
            'city_from_istat' => 'Unknown',
            'region_from' => 'Unknown',
            'region_from_istat' => 'Unknown',
        ];

        $query = <<<SQL
    SELECT
        m.cod_reg as cod_reg,
        m.name as comune,
        m.pro_com_t as istat
    FROM
        municipalities as m,
        hiking_routes as hr
    WHERE
        st_intersects(m.geometry, ST_Transform(ST_StartPoint(hr.geometry), 4326))
        AND hr.id = {$this->id};
SQL;

        try {
            $res = DB::select($query);
            if (count($res) > 0) {
                $info['city_from'] = $res[0]->comune;
                $info['city_from_istat'] = $res[0]->istat;
                $info['region_from'] = config('osm2cai.region_istat_name.' . $res[0]->cod_reg);
                $info['region_from_istat'] = $res[0]->cod_reg;

                if (empty($info['from'])) {
                    $info['from'] = $info['city_from'];
                }
            }
        } catch (\Throwable $th) {
            Log::error("HikingRoute::getFromInfo: ERROR on query: $query (ID:$this->id), " . $th->getMessage());
        }

        return $info;
    }

    /**
     * Get ending point information from ISTAT municipality database
     *
     * Queries the municipality_boundaries table to find which municipality
     * intersects with the route's ending point. Returns municipality and
     * region information based on ISTAT codes.
     *
     * @return array Associative array containing:
     *               - to: Ending point name
     *               - city_to: Municipality name
     *               - city_to_istat: Municipality ISTAT code
     *               - region_to: Region name
     *               - region_to_istat: Region ISTAT code
     */
    public function getToInfo(): array
    {
        $to = $this->osmfeatures_data['properties']['to'] ?? null;
        $info = [
            'to' => $to,
            'city_to' => 'Unknown',
            'city_to_istat' => 'Unknown',
            'region_to' => 'Unknown',
            'region_to_istat' => 'Unknown',
        ];

        $query = <<<SQL
    SELECT
        m.cod_reg as cod_reg,
        m.name as comune,
        m.pro_com_t as istat
    FROM
        municipalities as m,
        hiking_routes as hr
    WHERE
        st_intersects(m.geometry, ST_Transform(ST_Endpoint(ST_LineMerge(hr.geometry)), 4326))
        AND hr.id = {$this->id};
SQL;

        try {
            $res = DB::select($query);
            if (count($res) > 0) {
                $info['city_to'] = $res[0]->comune;
                $info['city_to_istat'] = $res[0]->istat;
                $info['region_to'] = config('osm2cai.region_istat_name.' . $res[0]->cod_reg);
                $info['region_to_istat'] = $res[0]->cod_reg;

                if (empty($info['to'])) {
                    $info['to'] = $info['city_to'];
                }
            }
        } catch (\Throwable $th) {
            Log::error("HikingRoute::getToInfo: ERROR on query: $query (ID:$this->id), " . $th->getMessage());
        }

        return $info;
    }

    /**
     * Get technical information about the route from DEM service
     *
     * Queries an external DEM service to get elevation data and derived metrics:
     * - Distance
     * - Elevation gain/loss
     * - Min/max elevation
     * - Estimated duration
     *
     * @return array Technical route information including elevation data and GPX URL
     */
    public function getTechInfoFromDem(): array
    {
        $info = [
            'gpx_url' => url('/api/v2/hiking-routes/' . $this->id . '.gpx'),
            'distance' => 'Unknown',
            'ascent' => 'Unknown',
            'descent' => 'Unknown',
            'duration_forward' => 'Unknown',
            'duration_backward' => 'Unknown',
            'ele_from' => 'Unknown',
            'ele_to' => 'Unknown',
            'ele_max' => 'Unknown',
            'ele_min' => 'Unknown',
        ];

        $data = $this->getEmptyGeojson();
        $data['properties']['id'] = $this->id; // dem api is expecting id in properties
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(
            'https://dem.maphub.it/api/v1/track', // TODO: Move to configuration
            $data
        );

        if ($response->successful()) {
            $info = $response->json()['properties'];
            $info['duration_forward'] = $info['duration_forward_hiking'];
            $info['duration_backward'] = $info['duration_backward_hiking'];
            unset($info['duration_forward_hiking'], $info['duration_backward_hiking']);
            unset($info['duration_forward_bike'], $info['duration_backward_bike']);
            $info['gpx_url'] = url('/api/v2/hiking-routes/' . $this->id . '.gpx');
        } else {
            $errorCode = $response->status();
            $errorBody = $response->body();
            Log::error($this->id . "UpdateEcTrack3DDemJob: FAILED: Error {$errorCode}: {$errorBody}");
        }

        return $info;
    }

    /**
     * Get CAI scale string
     *
     * Returns a short string for the CAI scale of the route in multiple languages.
     *
     * @return array Associative array with CAI scale labels in different languages
     */
    public function getCaiScaleString(): array
    {
        // if cai_scale is not set or is null, return an empty array
        if (! isset($this->osmfeatures_data['properties']['cai_scale']) || is_null($this->osmfeatures_data['properties']['cai_scale'])) {
            return [];
        }
        switch ($this->cai_scale) {
            case 'T':
                $v = [
                    'it' => 'Turistico',
                    'en' => 'Easy Hiking Trail',
                    'es' => 'Turístico',
                    'de' => 'Touristische Route',
                    'fr' => 'Sentier touristique',
                    'pt' => 'Turístico',
                ];
                break;

            case 'E':
                $v = [
                    'it' => 'Escursionistico',
                    'en' => 'Hiking Trail',
                    'es' => 'Excursionista',
                    'de' => 'Wanderweg',
                    'fr' => 'Sentier de randonnée',
                    'pt' => 'Caminhadas',
                ];
                break;

            case 'EE':
                $v = [
                    'it' => 'Escursionisti Esperti',
                    'en' => 'Experienced Hikers',
                    'es' => 'Excursionistas expertos',
                    'de' => 'Erfahrene Wanderer',
                    'fr' => 'Randonneurs chevronnés',
                    'pt' => 'Caminhantes Experientes',
                ];
                break;

            default:
                $v = [
                    'it' => 'Difficoltà sconosciuta',
                    'en' => 'Unknown difficulty',
                    'de' => 'Unbekannte Schwierigkeit',
                    'fr' => 'Difficulté inconnue',
                ];
                break;
        }

        return $v;
    }

    /**
     * Get CAI scale description
     *
     * Returns a long description of the CAI scale of the route in multiple languages.
     *
     * @return array Associative array with CAI scale descriptions in different languages
     */
    public function getCaiScaleDescription(): array
    {
        // if cai_scale is not set or is null, return an empty array
        if (! isset($this->osmfeatures_data['properties']['cai_scale']) || is_null($this->osmfeatures_data['properties']['cai_scale'])) {
            return [];
        }

        return $this->descriptionService->getCaiScaleDescription($this->osmfeatures_data['properties']['cai_scale']);
    }

    /**
     * Generate an automatic abstract description of the hiking route based on its metadata.
     *
     * Creates localized descriptions in multiple languages containing key information about:
     * - Start and end points
     * - Municipalities
     * - CAI difficulty rating
     * - Distance and elevation data
     * - Whether it's a loop trail or point-to-point
     * - General recommendations
     *
     * @param  array  $from  Starting point info from getFromInfo()
     * @param  array  $to  Ending point info from getToInfo()
     * @param  array  $tech  Technical data from getTechInfoFromDem()
     * @return array Associative array of localized abstracts keyed by language code
     */
    public function getAbstract(array $from, array $to, array $tech): array
    {
        return $this->descriptionService->generateAbstract([
            'ref' => $this->osmfeatures_data['properties']['osm_tags']['ref'] ?? null,
            'from' => $from,
            'to' => $to,
            'tech' => $tech,
            'roundtrip' => $this->osmfeatures_data['properties']['roundtrip'] ?? null,
            'cai_scale' => $this->getCaiScaleString(),
        ]);
    }

    /**
     * It returns a valid name for TDH export, even if the field name ha no value
     * The name is not translated (it,en,es,de,fr,pt)
     */
    public function getNameForTDH(): array
    {
        $v = [];
        if (! empty($this->name)) {
            $v = [
                'it' => $this->name,
                'en' => $this->name,
                'es' => $this->name,
                'de' => $this->name,
                'fr' => $this->name,
                'pt' => $this->name,
            ];
        } elseif (! empty($this->osmfeatures_data['properties']['ref'])) {
            $v = [
                'it' => 'Sentiero ' . $this->osmfeatures_data['properties']['ref'],
                'en' => 'Path ' . $this->osmfeatures_data['properties']['ref'],
                'es' => 'Camino ' . $this->osmfeatures_data['properties']['ref'],
                'de' => 'Weg ' . $this->osmfeatures_data['properties']['ref'],
                'fr' => 'Chemin ' . $this->osmfeatures_data['properties']['ref'],
                'pt' => 'Caminho ' . $this->ref,
            ];
        } else {
            $info = $this->getFromInfo();
            $v = [
                'it' => 'Sentiero del Comune di ' . $info['city_from'],
                'en' => 'Path in the municipality of ' . $info['city_from'],
                'es' => 'Camino en el municipio de ' . $info['city_from'],
                'de' => 'Weg in der Gemeinde ' . $info['city_from'],
                'fr' => 'Chemin dans la municipalité de ' . $info['city_from'],
                'pt' => 'Caminho no município de ' . $info['city_from'],
            ];
        }

        return $v;
    }

    /**
     * Dispatch jobs for geometric computations
     */
    public function dispatchGeometricComputationsJobs(string $queue = 'geometric-computations'): void
    {
        $buffer = config('osm2cai.hiking_route_buffer');

        // ---- Intersections ----
        CalculateIntersectionsJob::dispatch($this, Region::class)->onQueue($queue);
        CalculateIntersectionsJob::dispatch($this, Sector::class)->onQueue($queue);
        CalculateIntersectionsJob::dispatch($this, Area::class)->onQueue($queue);
        CalculateIntersectionsJob::dispatch($this, Province::class)->onQueue($queue);
        CalculateIntersectionsJob::dispatch($this, MountainGroups::class)->onQueue($queue);

        // ---- Nearby entities in a defined buffer ----
        CheckNearbyHutsJob::dispatch($this, $buffer)->onQueue($queue);
        CheckNearbyNaturalSpringsJob::dispatch($this, $buffer)->onQueue($queue);
        CheckNearbyEcPoisJob::dispatch($this, $buffer)->onQueue($queue);
    }

    /**
     * Compute the ref_REI attribute based on main sector for the route
     */
    public function getRefReiCompAttribute(): string
    {
        // Ensure the 'sectors' relation is loaded to prevent N+1 queries.
        // Eager loading should be handled by the caller (e.g., Nova indexQuery).
        if (! $this->relationLoaded('sectors')) {
            // This is a fallback and ideally should not be reached
            // in performance-critical contexts like Nova's index view.
            // If reached, it means 'sectors' was not eager loaded.
            $this->load('sectors');
        }

        // Sort the loaded sectors collection by 'percentage' in the pivot table.
        $mainSector = $this->sectors->sortByDesc(function ($sector) {
            return $sector->pivot->percentage ?? 0; // Handles case where pivot or percentage might be null
        })->first();

        $ref = $this->osmfeatures_data['properties']['ref'] ?? '';

        if (! $mainSector || empty($ref)) {
            return '';
        }

        $refLength = strlen($ref);
        $refSuffix = substr($ref, 1);

        if ($refLength === 3) {
            return $mainSector->full_code . $refSuffix . '0';
        }

        if ($refLength === 4) {
            return $mainSector->full_code . $refSuffix;
        }

        return $mainSector->full_code . '????';
    }

    /**
     * Extract and populate properties from osmfeatures_data for Elasticsearch indexing.
     *
     * @param  array|string|null  $osmfeaturesData
     */
    public static function extractPropertiesFromOsmfeatures($osmfeaturesData, $id): array
    {
        if (is_string($osmfeaturesData)) {
            $osmfeaturesData = json_decode($osmfeaturesData, true);
        }

        if (
            empty($osmfeaturesData)
            || ! isset($osmfeaturesData['properties'])
            || ! is_array($osmfeaturesData['properties'])
        ) {
            return [];
        }

        $props = $osmfeaturesData['properties'];
        $layers = self::find($id)?->layers?->pluck('id')->toArray() ?? [];
        $properties = array_merge(
            self::extractBaseProperties($props),
            self::extractDemProperties($props),
            [
                'roundtrip' => $props['roundtrip'] ?? null,
                'network'   => $props['network'] ?? null,
                'osm_id'    => $props['osm_id'] ?? null,
                'layers' => $layers,
            ]
        );

        return $properties;
    }

    private static function extractBaseProperties(array $props): array
    {
        return [
            'ref'       => $props['ref'] ?? '',
            'cai_scale' => $props['cai_scale'] ?? '',
            'name'      => $props['name'] ?? null,
            'from'      => $props['from'] ?? '',
            'to'        => $props['to'] ?? '',
            'description' => $props['description_it'] ?? $props['description'] ?? '',
            'excerpt'     => $props['excerpt'] ?? '',
        ];
    }

    private static function extractDemProperties(array $props): array
    {
        if (! empty($props['dem_enrichment']) && is_array($props['dem_enrichment'])) {
            $dem = $props['dem_enrichment'];

            return [
                'distance'          => $dem['distance'] ?? null,
                'ascent'            => $dem['ascent'] ?? null,
                'descent'           => $dem['descent'] ?? null,
                'ele_from'          => $dem['ele_from'] ?? null,
                'ele_to'            => $dem['ele_to'] ?? null,
                'ele_max'           => $dem['ele_max'] ?? null,
                'ele_min'           => $dem['ele_min'] ?? null,
                'duration_forward'  => $dem['duration_forward_hiking'] ?? null,
                'duration_backward' => $dem['duration_backward_hiking'] ?? null,
            ];
        }

        return [
            'distance'          => $props['distance'] ?? null,
            'ascent'            => $props['ascent'] ?? null,
            'descent'           => $props['descent'] ?? null,
            'duration_forward'  => $props['duration_forward'] ?? null,
            'duration_backward' => $props['duration_backward'] ?? null,
        ];
    }

    /**
     * Override the getGeojson method to customize the GeoJSON for HikingRoute
     *
     * This method extends the base GeoJSON by adding specific information
     * for hiking routes such as TDH data, sector information, etc.
     */
    public function getGeojson(): array
    {
        // Get the base GeoJSON from parent
        $baseGeojson = parent::getGeojson();

        if ($baseGeojson && $this->app && $this->app->sku === 'it.webmapp.osm2cai') {
            // Extend properties with specific data for HikingRoute
            $enhancedGeojson = $this->enhanceHikingRouteProperties($baseGeojson);

            return $enhancedGeojson;
        }

        return $baseGeojson;
    }

    /**
     * Extend GeoJSON properties with specific data for HikingRoute
     */
    private function enhanceHikingRouteProperties(array $baseGeojson): array
    {
        $osmDataProperties = $this->osmfeatures_data['properties'] ?? [];
        // Gestisci la descrizione: se è una stringa, mettila in ['it'], altrimenti crea l'array
        if (isset($baseGeojson['properties']['description'])) {
            $description = $baseGeojson['properties']['description'];
            if (is_string($description)) {
                $baseGeojson['properties']['description'] = ['it' => $description];
            }
        } else {
            $baseGeojson['properties']['description'] = [];
        }
        $baseGeojson['properties']['description']['it'] ??= '';

        if ($this->osm2cai_status) {
            $baseGeojson['properties']['description']['it'] .= <<<HTML
                <br>Stato di accatastamento: <strong>{$this->osm2cai_status}</strong> ({$this->getSDADescription()})<br>
                HTML;
        }

        $baseGeojson['properties']['description']['it'] .= <<<HTML
            <a href="https://osm2cai.cai.it/resources/hiking-routes/{$this->id}" target="_blank">Modifica questo percorso</a>
            HTML;

        if (isset($osmDataProperties['website'])) {
            $host = parse_url($osmDataProperties['website'], PHP_URL_HOST) ?: $osmDataProperties['website'];
            $baseGeojson['properties']['related_url'][$host] = $osmDataProperties['website'];
        }

        return $baseGeojson;
    }

    /**
     * Get poles within a buffer distance from the hiking route geometry.
     *
     * This method retrieves all poles that are within a specified buffer distance
     * from the hiking route. It considers both the main geometry and the raw geometry
     * data if available, performing a spatial union of both geometries to ensure
     * comprehensive coverage.
     *
     * The method uses PostGIS spatial functions to:
     * 1. Extract the main route geometry
     * 2. Optionally merge with geometry_raw_data if it exists
     * 3. Find all poles within the specified buffer distance
     *
     * @param  float  $bufferDistance  The buffer distance in meters (default: 10m)
     * @return \Illuminate\Database\Eloquent\Collection Collection of Poles within the buffer
     *
     * @example
     * // Get poles within 10 meters of the route
     * $poles = $hikingRoute->getPolesWithBuffer();
     *
     * // Get poles within 50 meters of the route
     * $poles = $hikingRoute->getPolesWithBuffer(50);
     *
     * @throws \Illuminate\Database\QueryException If there's an error in the spatial query
     *
     * @see Poles
     * @see https://postgis.net/docs/ST_DWithin.html
     * @see https://postgis.net/docs/ST_Union.html
     */
    public function getPolesWithBuffer(float $bufferDistance = 15)
    {
        $geojson = $this->getHikingRouteGeojson($bufferDistance);

        return Poles::select('poles.*')
            ->whereRaw(
                'ST_DWithin(poles.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                [$geojson, $bufferDistance]
            )
            ->get();
    }

    public function getUgcPoisWithBuffer(float $bufferDistance = 10, ?string $startDate = null, ?string $endDate = null)
    {
        // Ottieni la geometria principale
        $geojson = $this->getHikingRouteGeojson($bufferDistance);

        $query = UgcPoi::select('ugc_pois.*');

        // Filtro per start_date
        if ($startDate !== null) {
            $query->whereDate('ugc_pois.created_at', '>=', $startDate);
        }

        // Filtro per end_date
        if ($endDate !== null) {
            $query->whereDate('ugc_pois.created_at', '<=', $endDate);
        }

        return $query
            ->whereRaw(
                'ST_DWithin(ugc_pois.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                [$geojson, $bufferDistance]
            )
            ->get();
    }

    public function getUgcTracksWithBuffer(float $bufferDistance = 10, ?string $startDate = null, ?string $endDate = null)
    {
        $geojson = $this->getHikingRouteGeojson($bufferDistance);

        $query = UgcTrack::select('ugc_tracks.*');

        // Filtro per start_date
        if ($startDate !== null) {
            $query->whereDate('ugc_tracks.created_at', '>=', $startDate);
        }

        // Filtro per end_date
        if ($endDate !== null) {
            $query->whereDate('ugc_tracks.created_at', '<=', $endDate);
        }

        return $query
            ->whereRaw(
                'ST_DWithin(ugc_tracks.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                [$geojson, $bufferDistance]
            )
            ->get();
    }

    private function getHikingRouteGeojson(float $bufferDistance = 10): string
    {
        // Ottieni la geometria principale
        $geojson = DB::table('hiking_routes')
            ->where('id', $this->id)
            ->value(DB::raw('ST_AsGeoJSON(geometry)'));

        return $geojson ?? '{}';
    }

    /**
     * It returns the description of the osm2cai status
     *
     * @param  int  $sda  track osm2cai status
     */
    private function getSDADescription()
    {
        $description = '';
        switch ($this->osm2cai_status) {
            case '0':
                $description = 'Non rilevato, senza scala di difficoltà';
                break;
            case '1':
                $description = 'Percorsi non rilevati, con scala di difficoltà';
                break;
            case '2':
                $description = 'Percorsi rilevati, senza scala di difficoltá';
                break;
            case '3':
                $description = 'Percorsi rilevati, con scala di difficoltá';
                break;
            case '4':
                $description = 'Percorsi importati in INFOMONT';
                break;
        }

        return $description;
    }
}
