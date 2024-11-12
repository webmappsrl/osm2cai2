<?php

namespace App\Models;

use App\Models\Area;
use App\Models\User;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Province;
use App\Models\Itinerary;
use App\Traits\SpatialDataTrait;
use App\Traits\TagsMappingTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Jobs\RecalculateIntersections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Stopwatch\Section;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Services\HikingRouteDescriptionService;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;


class HikingRoute extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory;
    use OsmfeaturesImportableTrait;
    use OsmfeaturesSyncableTrait;
    use TagsMappingTrait;
    use OsmfeaturesGeometryUpdateTrait;
    use SpatialDataTrait;

    protected $fillable = [
        'geometry',
        'osm2cai_status',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
        'tdh'
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'array',
        'issues_last_update' => 'date',
        'tdh' => 'array'
    ];

    private HikingRouteDescriptionService $descriptionService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->descriptionService = app(HikingRouteDescriptionService::class);
    }

    protected static function booted()
    {
        // static::saved(function ($hikingRoute) {
        //     if ($hikingRoute->is_syncing) {
        //         $hikingRoute->is_syncing = false;
        //         return;
        //     }
        //     Artisan::call('osm2cai:add_cai_huts_to_hiking_routes', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);
        //     Artisan::call('osm2cai:add_natural_springs_to_hiking_routes', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);

        //     if ($hikingRoute->osm2cai_status == 4) {
        //         Artisan::call('osm2cai:tdh', ['id' => $hikingRoute->id]);
        //         Artisan::call('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);
        //     }
        // });

        // static::created(function ($hikingRoute) {
        //     if ($hikingRoute->is_syncing) {
        //         $hikingRoute->is_syncing = false;
        //         return;
        //     }

        //     Artisan::call('osm2cai:add_cai_huts_to_hiking_routes', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);
        //     Artisan::call('osm2cai:add_natural_springs_to_hiking_routes', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);

        //     if ($hikingRoute->osm2cai_status == 4) {
        //         Artisan::call('osm2cai:tdh', ['id' => $hikingRoute->id]);
        //         Artisan::call('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute', 'id' => $hikingRoute->id]);
        //     }
        // });

        static::updated(function ($hikingRoute) {
            if ($hikingRoute->isDirty('geometry')) {
                RecalculateIntersections::dispatch($hikingRoute);
            }
        });
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
        return ['status' => 1]; //get only hiking routes with osm2cai status greater than 0 (current values in osmfeatures: 1,2,3)
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

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update osm2cai_status if necessary
        if (isset($osmfeaturesData['osm2cai_status']) && $osmfeaturesData['osm2cai_status'] !== null) {
            if ($model->osm2cai_status !== 4 && $model->osm2cai_status !== $osmfeaturesData['osm2cai_status']) {
                $updateData['osm2cai_status'] = $osmfeaturesData['osm2cai_status'];
                Log::channel('wm-osmfeatures')->info('osm2cai_status updated for HikingRoute ' . $osmfeaturesId);
            }
        }

        // Execute the update only if there are data to update
        if (!empty($updateData)) {
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
        $wmt = "https://hiking.waymarkedtrails.org/#route?id=" . $osmId;
        $analyzer = "https://ra.osmsurround.org/analyzeRelation?relationId=" . $osmId . "&noCache=true&_noCache=on";
        $endpoint = 'https://geohub.webmapp.it/api/osf/track/osm2cai/';
        $api = $endpoint . $this->id;

        $headers = get_headers($api);
        $statusLine = $headers[0];

        if (strpos($statusLine, '200 OK') !== false) {
            // The API returned a success response
            $data = json_decode(file_get_contents($api), true);
            if (!empty($data)) {
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
            'analyzerLink' => $analyzer
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
        return $this->belongsToMany(Area::class);
    }

    public function sectors()
    {
        return $this->belongsToMany(Sector::class)->withPivot(['percentage']);
    }

    public function issueUser()
    {
        return $this->belongsTo(User::class, 'id', 'issues_user_id');
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'hiking_route_section');
    }

    public function itineraries()
    {
        return $this->belongsToMany(Itinerary::class);
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
        if (!empty($this->validation_date)) {
            return true;
        }

        return false;
    }

    /*
     * 0: cai_scale null, source null
     * 1: cai_scale not null, source null
     * 2: cai_scale null, source contains "survey:CAI"
     * 3: cai_scale not null, source contains "survey:CAI"
     * 4: validation_date not_null
     */
    public function setOsm2CaiStatus(): void
    {
        $status = 0;
        if ($this->validated()) {
            $status = 4;
        } else if (!is_null($this->cai_scale_osm) && !preg_match('/survey:CAI/', $this->source_osm)) {
            $status = 1;
        } else if (is_null($this->cai_scale_osm) && preg_match('/survey:CAI/', $this->source_osm)) {
            $status = 2;
        } else if (!is_null($this->cai_scale_osm) && preg_match('/survey:CAI/', $this->source_osm)) {
            $status = 3;
        }
        $this->osm2cai_status = $status;
    }

    /**
     * Check if the route geometry is valid
     * 
     * A route geometry is considered invalid if:
     * - It has multiple segments (nseg > 1)
     * - AND it is validated (osm2cai_status == 4)
     * 
     * @return bool True if geometry is valid, false otherwise
     */
    public function hasCorrectGeometry()
    {
        $geojson = $this->query()->where('id', $this->id)->selectRaw('ST_AsGeoJSON(geometry) as geom')->get()->pluck('geom')->first();
        $geom = json_decode($geojson, TRUE);
        $type = $geom['type'];
        $nseg = count($geom['coordinates']);
        if ($nseg > 1 && $this->osm2cai_status == 4)
            return false;

        return true;
    }

    /**
     * Get a hiking route by its OpenStreetMap ID
     * 
     * Looks up a hiking route using the OSM ID stored in the osmfeatures_data properties.
     * Returns the first matching route or null if not found.
     *
     * @param string $osmId The OpenStreetMap ID to search for
     * @return \App\Models\HikingRoute|null The matching hiking route if found, null otherwise
     */
    public static function getHikingRouteByOsmId(string $osmId): ?HikingRoute
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
     * @return \App\Models\Sector|null The main sector if found, null otherwise
     */
    public function mainSector()
    {
        $q = "SELECT sector_id from hiking_route_sector where hiking_route_id={$this->id} order by percentage desc limit 1;";
        $res = DB::select($q);
        if (count($res) > 0) {
            foreach ($res as $item) {
                $sector_id = $item->sector_id;
            }
            return Sector::find($sector_id);
        }
        return null;
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
        $from = $this->osmfeatures_data['properties']['from'];
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
        $to = $this->osmfeatures_data['properties']['to'];
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
        $data['properties']['id'] = $this->id; //dem api is expecting id in properties
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(
            'https://dem.maphub.it/api/v1/track', //TODO: Move to configuration
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
        //if cai_scale is not set or is null, return an empty array
        if (!isset($this->osmfeatures_data['properties']['cai_scale']) || is_null($this->osmfeatures_data['properties']['cai_scale'])) {
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
                    'pt' => 'Turístico'
                ];
                break;

            case 'E':
                $v = [
                    'it' => 'Escursionistico',
                    'en' => 'Hiking Trail',
                    'es' => 'Excursionista',
                    'de' => 'Wanderweg',
                    'fr' => 'Sentier de randonnée',
                    'pt' => 'Caminhadas'
                ];
                break;

            case 'EE':
                $v = [
                    'it' => 'Escursionisti Esperti',
                    'en' => 'Experienced Hikers',
                    'es' => 'Excursionistas expertos',
                    'de' => 'Erfahrene Wanderer',
                    'fr' => 'Randonneurs chevronnés',
                    'pt' => 'Caminhantes Experientes'
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
        //if cai_scale is not set or is null, return an empty array
        if (!isset($this->osmfeatures_data['properties']['cai_scale']) || is_null($this->osmfeatures_data['properties']['cai_scale'])) {
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
     * @param array $from Starting point info from getFromInfo()
     * @param array $to Ending point info from getToInfo() 
     * @param array $tech Technical data from getTechInfoFromDem()
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
     *
     * @return array
     */
    public function getNameForTDH(): array
    {
        $v = [];
        if (!empty($this->name)) {
            $v = [
                'it' => $this->name,
                'en' => $this->name,
                'es' => $this->name,
                'de' => $this->name,
                'fr' => $this->name,
                'pt' => $this->name,
            ];
        } else if (!empty($this->ref)) {
            $v = [
                'it' => 'Sentiero ' . $this->ref,
                'en' => 'Path ' . $this->ref,
                'es' => 'Camino ' . $this->ref,
                'de' => 'Weg ' . $this->ref,
                'fr' => 'Chemin ' . $this->ref,
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
}
