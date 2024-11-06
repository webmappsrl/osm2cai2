<?php

namespace App\Models;

use App\Models\Area;
use App\Models\User;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Province;
use App\Models\Itinerary;
use App\Traits\TagsMappingTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\RecalculateIntersections;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Stopwatch\Section;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
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

    protected $fillable = [
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'array',
        'issues_last_update' => 'date'
    ];

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
}
