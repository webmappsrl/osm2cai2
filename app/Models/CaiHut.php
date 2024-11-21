<?php

namespace App\Models;

use App\Console\Commands\CheckNearbyHikingRoutes;
use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Models\Region;
use App\Traits\AwsCacheable;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;

class CaiHut extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, SpatialDataTrait, OsmfeaturesImportableTrait, OsmfeaturesGeometryUpdateTrait, AwsCacheable;

    protected $fillable = [
        'osmfeatures_id',
        'osmfeatures_data',
        'name',
        'geometry',
        'updated_at',
        'created_at',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
    ];

    protected static function booted()
    {
        //TODO: review from legacy osm2cai
        static::created(function ($caiHut) {
            CheckNearbyHikingRoutesJob::dispatch($caiHut, config(config('osm2cai.hiking_route_buffer')));
        });

        static::saved(function ($caiHut) {
            if (app()->environment('production')) {
                CacheMiturAbruzzoDataJob::dispatch('CaiHut', $caiHut->id);
            }
        });
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Returns the OSMFeatures API endpoint for listing features for the model.
     */
    public static function getOsmfeaturesEndpoint(): string
    {
        return 'https://osmfeatures.maphub.it/api/v1/features/places/';
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
        return [];
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

        if (! $osmfeaturesData || empty($osmfeaturesData)) {
            Log::channel('wm-osmfeatures')->info('No data found for CaiHut ' . $osmfeaturesId);

            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        if (isset($osmfeaturesData['properties'])) {
            if ($osmfeaturesData['properties']['name'] !== null && $osmfeaturesData['properties']['name'] !== $model->name) {
                $updateData['name'] = $osmfeaturesData['properties']['name'];
            } elseif ($osmfeaturesData['properties']['name'] === null) {
                Log::channel('wm-osmfeatures')->info('No name found for CaiHut ' . $osmfeaturesId);
            }
        }

        $model->update($updateData);
    }
}
