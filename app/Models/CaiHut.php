<?php

namespace App\Models;

use App\Models\Region;
use App\Traits\GeoBufferTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Database\Eloquent\Model;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class CaiHut extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, GeoIntersectTrait, GeoBufferTrait, SpatialDataTrait, OsmfeaturesImportableTrait, OsmfeaturesGeometryUpdateTrait;

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
        // static::created(function ($caiHut) {
        //     Artisan::call('osm2cai:add_cai_huts_to_hiking_routes', ['model' => 'CaiHuts', 'id' => $caiHut->id]);
        // });
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

        if (! $osmfeaturesData) {
            Log::channel('wm-osmfeatures')->info('No data found for CaiHut ' . $osmfeaturesId);
            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        if ($osmfeaturesData['properties']['name'] !== null && $osmfeaturesData['properties']['name'] !== $model->name) {
            $updateData['name'] = $osmfeaturesData['properties']['name'];
        } else if ($osmfeaturesData['properties']['name'] === null) {
            Log::channel('wm-osmfeatures')->info('No name found for CaiHut ' . $osmfeaturesId);
        }

        $model->update($updateData);
    }
}
