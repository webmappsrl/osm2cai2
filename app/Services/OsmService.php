<?php

namespace App\Services;

use App\Models\HikingRoute;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Symfony\Component\String\Exception\RuntimeException;

class OsmService
{
    protected $http;

    protected $geometryService;

    protected $baseApiUrl = 'https://www.openstreetmap.org/api/0.6/';

    protected $baseWaymarkedUrl = 'https://hiking.waymarkedtrails.org/api/v1/details/relation/';

    public function __construct()
    {
        $this->http = Http::class;
        $this->geometryService = GeometryService::getService();
    }

    /**
     * Return an instance of this class
     *
     * @return OsmService
     */
    public static function getService()
    {
        return app(__CLASS__);
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getService();

        if (! $instance) {
            throw new RuntimeException('OsmService has not been set.');
        }

        return $instance->$method(...$args);
    }

    /**
     * Get allowed OSM relation API fields
     *
     * @return array
     */
    public function getRelationApiFieldsKey()
    {
        return [
            'ref',
            'old_ref',
            'source_ref',
            'survey_date',
            'name',
            'rwn_name',
            'ref_REI',
            'from',
            'to',
            'osmc_symbol',
            'network',
            'roundtrip',
            'symbol',
            'symbol_it',
            'ascent',
            'descent',
            'distance',
            'duration_forward',
            'duration_backward',
            'operator',
            'state',
            'description',
            'description_it',
            'website',
            'wikimedia_commons',
            'maintenance',
            'maintenance_it',
            'note',
            'note_it',
            'note_project_page',
            'cai_scale',
            'network',
            'source',
            'ele_from',
            'ele_to',
            'ele_max',
            'ele_min',
            'reg_ref',
        ];
    }

    /**
     * Check if a hiking route exists in OSM
     *
     * @param  string|int  $relationId
     * @return bool
     */
    public function hikingRouteExists($relationId)
    {
        return $this->http::head($this->baseApiUrl.'relation/'.intval($relationId))->ok();
    }

    /**
     * Return OSM API data by relation id provided
     *
     * @param  string|int  $relationId
     * @return array|false
     */
    public function getHikingRoute($relationId)
    {
        $response = $this->http::get($this->baseApiUrl.'relation/'.intval($relationId));

        if (! $response->ok()) {
            return false;
        }

        $allowedKeys = $this->getRelationApiFieldsKey();
        $xml = $response->body();
        $relation = (new SimpleXMLElement($xml))->relation;
        $return = ['osm_id' => $relationId];

        foreach ($relation->tag as $tag) {
            $key = str_replace(':', '_', (string) $tag['k']);
            if (in_array($key, $allowedKeys)) {
                $return[$key] = (string) $tag['v'];
            }
        }

        return $return;
    }

    /**
     * Get hiking route GeoJSON from Waymarked Trails
     *
     * @param  string|int  $relationId
     * @return string|false
     */
    public function getHikingRouteGeojson($relationId)
    {
        $response = $this->http::get($this->baseWaymarkedUrl.intval($relationId).'/geometry/geojson');

        return $response->ok() ? $response->body() : false;
    }

    /**
     * Get hiking route GPX from Waymarked Trails
     *
     * @param  string|int  $relationId
     * @return string|false
     */
    public function getHikingRouteGpx($relationId)
    {
        $response = $this->http::get($this->baseWaymarkedUrl.intval($relationId).'/geometry/gpx');

        return $response->ok() ? $response->body() : false;
    }

    /**
     * Convert GPX to PostGIS geometry with default SRID
     *
     * @param  string|int  $relationId
     * @return string|false
     */
    public function getHikingRouteGeometry($relationId)
    {
        return $this->convertGpxToGeometry($relationId, 'geojsonToMultilinestringGeometry');
    }

    /**
     * Convert GPX to PostGIS geometry with OSM SRID (3857)
     *
     * @param  string|int  $relationId
     * @return string|false
     */
    public function getHikingRouteGeometry3857($relationId)
    {
        return $this->convertGpxToGeometry($relationId, 'geojsonToMultilinestringGeometry3857');
    }

    /**
     * Helper method to convert GPX to geometry
     *
     * @param  string|int  $relationId
     * @param  string  $conversionMethod
     * @return string|false
     */
    protected function convertGpxToGeometry($relationId, $conversionMethod)
    {
        $gpx = $this->getHikingRouteGpx($relationId);
        if (! $gpx) {
            return false;
        }

        $geojson = $this->geometryService->textToGeojson($gpx);

        return $this->geometryService->$conversionMethod(json_encode($geojson));
    }

    /**
     * Update a HikingRoute model with fresh OSM data
     *
     * @param  array|null  $osmTags
     * @return HikingRoute|false
     */
    public function updateHikingRouteModelWithOsmData(HikingRoute $model, $osmTags = null)
    {
        // Validate model has required OSM data
        if (! isset($model->osmfeatures_data['properties']['osm_id'])) {
            Log::error("OsmService: Missing osm_id for HikingRoute ID {$model->id}");

            return false;
        }

        $relationId = $model->osmfeatures_data['properties']['osm_id'];

        // Fetch OSM data
        $osmData = $this->fetchOsmData($relationId, $osmTags);
        if (! $osmData) {
            return false;
        }

        [$osmTags, $osmGeo] = $osmData;

        // Prepare osmfeatures_data
        $osmfeaturesData = $this->prepareOsmfeaturesData($model, $osmGeo, $osmTags);
        if (! $osmfeaturesData) {
            return false;
        }

        // Calculate OSM2CAI status
        $calculated_status = $this->calculateOsm2caiStatus($osmTags);

        // Update model
        if (! $this->updateModelWithOsmData($model, $osmGeo, $osmfeaturesData, $calculated_status)) {
            return false;
        }

        // Update intersections
        $this->updateIntersections($model);

        return $model;
    }

    /**
     * Fetch OSM data (tags and geometry)
     *
     * @param  string|int  $relationId
     * @param  array|null  $osmTags
     * @return array|false
     */
    protected function fetchOsmData($relationId, $osmTags = null)
    {
        // Fetch fresh OSM tags if not provided
        if (is_null($osmTags)) {
            $osmTags = $this->getHikingRoute($relationId);
        }

        if ($osmTags === false) {
            Log::error("OsmService: Failed to fetch OSM tags for relation ID {$relationId}");

            return false;
        }

        // Fetch fresh geometry
        $osmGeo = $this->getHikingRouteGeometry($relationId);
        if ($osmGeo === false) {
            Log::error("OsmService: Failed to fetch OSM geometry for relation ID {$relationId}");

            return false;
        }

        return [$osmTags, $osmGeo];
    }

    /**
     * Prepare osmfeatures_data array
     *
     * @param  string  $osmGeo
     * @param  array  $osmTags
     * @return array|false
     */
    protected function prepareOsmfeaturesData(HikingRoute $model, $osmGeo, $osmTags)
    {
        $osmfeaturesData = is_array($model->osmfeatures_data)
            ? $model->osmfeatures_data
            : json_decode($model->osmfeatures_data ?? '{}', true);

        if (! is_array($osmfeaturesData)) {
            Log::error("OsmService: Invalid osmfeatures_data JSON for HikingRoute ID {$model->id}");

            return false;
        }

        // Ensure properties key exists
        if (! isset($osmfeaturesData['properties'])) {
            $osmfeaturesData['properties'] = [];
        }

        // Update geometry in osmfeatures_data
        $osmfeaturesData['geometry'] = json_decode(
            DB::select('SELECT ST_AsGeoJSON(?) as geojson', [$osmGeo])[0]->geojson,
            true
        );

        // Update tags and status
        $osmfeaturesData['properties']['osm_tags'] = $osmTags;
        $osmfeaturesData['properties']['osm2cai_status'] = $this->calculateOsm2caiStatus($osmTags);

        return $osmfeaturesData;
    }

    /**
     * Calculate OSM2CAI status based on tags
     *
     * @param  array  $osmTags
     * @return int
     */
    protected function calculateOsm2caiStatus($osmTags)
    {
        $cai_scale_present = ! empty($osmTags['cai_scale']);
        $survey_CAI_present = isset($osmTags['source']) && $osmTags['source'] === 'survey:CAI';

        if ($cai_scale_present && $survey_CAI_present) {
            return 3;
        } elseif ($cai_scale_present) {
            return 1;
        } elseif ($survey_CAI_present) {
            return 2;
        }

        return 0; // Default status
    }

    /**
     * Update model with OSM data
     *
     * @param  string  $osmGeo
     * @param  array  $osmfeaturesData
     * @param  int  $calculated_status
     * @return bool
     */
    protected function updateModelWithOsmData(HikingRoute $model, $osmGeo, $osmfeaturesData, $calculated_status)
    {
        // Update model fields
        $model->geometry = $osmGeo;
        $model->osm2cai_status = $calculated_status;
        $model->osmfeatures_data = $osmfeaturesData;

        if (! $model->save()) {
            Log::error("OsmService: Failed to save HikingRoute ID {$model->id} after OSM sync.");

            return false;
        }

        return true;
    }

    /**
     * Update intersections for the model
     *
     * @return void
     */
    protected function updateIntersections(HikingRoute $model)
    {
        try {
            $sectorsIntersecting = $model->getIntersections(new Sector);
            $model->sectors()->sync($sectorsIntersecting->pluck('id'));
        } catch (\Throwable $e) {
            Log::error("OsmService: Error syncing sectors for HikingRoute ID {$model->id}: ".$e->getMessage());
            // Continue even if sector sync fails, but log the error
        }
    }
}
