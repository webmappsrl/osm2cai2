<?php

namespace App\Models;

use App\Traits\TagsMappingTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class HikingRoute extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, TagsMappingTrait, OsmfeaturesImportableTrait;

    protected $fillable = [
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
    ];

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

        $osmfeaturesData = json_decode($model->osmfeatures_data, true);

        if (! $osmfeaturesData) {
            Log::channel('wm-osmfeatures')->info('No data found for HikingRoute ' . $osmfeaturesId);

            return;
        }

        //format the geometry
        if ($osmfeaturesData['geometry']) {
            $geometry = DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('" . json_encode($osmfeaturesData['geometry']) . "'))")[0]->st_astext;
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry found for HikingRoute ' . $osmfeaturesId);
            $geometry = null;
        }

        $model->update([
            'geometry' => $geometry,
        ]);
    }
}
