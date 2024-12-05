<?php

namespace App\Models;

use App\Models\User;
use App\Models\HikingRoute;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Jobs\CalculateIntersectionsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sector extends Model
{
    use HasFactory, SpatialDataTrait, CsvableModelTrait, SallableTrait, IntersectingRouteStats;

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($sector) {
            if ($sector->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($sector, HikingRoute::class)->onQueue('geometric-computations');
            }
        });
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class);
    }

    /**
     * Alias
     */
    public function parent()
    {
        return $this->area();
    }

    /**
     * Alias
     */
    public function children()
    {
        return $this->hikingRoutes();
    }

    /**
     * Generates a complete GeoJSON representation of all hiking routes in the sector
     * that have a valid osm2cai_status (1-4).
     *
     * The GeoJSON includes detailed properties for each hiking route including:
     * - Basic info (id, name, ref, etc)
     * - Status and metadata
     * - Route details (distance, duration, elevation)
     * - Associated sectors
     * - Full geometry
     *
     * @return string JSON encoded GeoJSON FeatureCollection
     */
    public function getGeojsonComplete(): string
    {
        // Initialize GeoJSON structure
        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [],
        ];

        // Get hiking routes with valid status
        $hikingRoutes = $this->hikingRoutes->where('osm2cai_status', '!=', 0);

        if (count($hikingRoutes)) {
            foreach ($hikingRoutes as $hikingRoute) {
                $osmfeaturesDataProperties = $hikingRoute->osmfeatures_data['properties'];

                // Build properties object
                $properties = [
                    // Basic info
                    'id' => $hikingRoute->id,
                    'created_at' => $hikingRoute->created_at,
                    'updated_at' => $hikingRoute->updated_at,
                    'name' => $hikingRoute->name,
                    'ref' => $hikingRoute->osmfeatures_data['properties']['ref'],
                    'old_ref' => $osmfeaturesDataProperties['old_ref'],
                    'source_ref' => $osmfeaturesDataProperties['source_ref'],

                    // Status and metadata
                    'created_at' => $hikingRoute->created_at,
                    'updated_at' => $hikingRoute->updated_at,
                    'osm2cai_status' => $hikingRoute->osm2cai_status,
                    'osm_id' => $osmfeaturesData['properties']['osm_id'],
                    'osm2cai' => url('/nova/resources/hiking-routes/' . $hikingRoute->id . '/edit'),
                    'survey_date' => $osmfeaturesDataProperties['survey_date'],
                    'accessibility' => $hikingRoute->issues_status,

                    // Route details
                    'from' => $osmfeaturesDataProperties['from'],
                    'to' => $osmfeaturesDataProperties['to'],
                    'distance' => $osmfeaturesDataProperties['distance'],
                    'cai_scale' => $osmfeaturesDataProperties['cai_scale'],
                    'roundtrip' => $osmfeaturesDataProperties['roundtrip'],
                    'duration_forward' => $osmfeaturesDataProperties['duration_forward'],
                    'duration_backward' => $osmfeaturesDataProperties['duration_backward'],
                    'ascent' => $osmfeaturesDataProperties['ascent'],
                    'descent' => $osmfeaturesDataProperties['descent'],

                    // REI references
                    'ref_REI' => $hikingRoute->osmfeatures_data['properties']['ref_REI'],
                ];

                // Get geometry as GeoJSON
                $geometry = HikingRoute::where('id', '=', $hikingRoute->id)
                    ->select(DB::raw('ST_AsGeoJSON(geometry) as geom'))
                    ->first()
                    ->geom;
                $geometry = json_decode($geometry, true);

                // Build complete feature
                $feature = [
                    'type' => 'Feature',
                    'properties' => $properties,
                    'geometry' => $geometry,
                ];

                $geojson['features'][] = $feature;
            }
        }

        return json_encode($geojson);
    }
}
