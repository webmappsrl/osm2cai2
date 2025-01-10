<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;

class Poles extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory,  OsmfeaturesSyncableTrait;

    protected $fillable = [
        'name',
        'geometry',
        'osm_type',
        'osm_id',
        'tags',
        'ele',
        'ref',
        'destination',
        'support',
        'score',
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
        return 'https://osmfeatures.maphub.it/api/v1/features/poles/';
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

        if (! $model->osmfeatures_data) {
            Log::channel('wm-osmfeatures')->info('No osmfeatures_data found for Pole ' . $osmfeaturesId);

            return;
        }
        $osmfeaturesData = is_string($model->osmfeatures_data) ? json_decode($model->osmfeatures_data, true) : $model->osmfeatures_data;

        //format the geometry
        if ($osmfeaturesData['geometry']) {
            $geometry = DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('" . json_encode($osmfeaturesData['geometry']) . "'))")[0]->st_astext;
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry found for Pole ' . $osmfeaturesId);
            $geometry = null;
        }
        $properties = $osmfeaturesData['properties'];
        if (! $properties) {
            Log::channel('wm-osmfeatures')->info('No properties found for Pole ' . $osmfeaturesId);

            return;
        }

        if ($properties['ref'] === null || $properties['ref'] === '') {
            Log::channel('wm-osmfeatures')->info('No ref found for Pole ' . $osmfeaturesId);
            $ref = 'noname(' . $osmfeaturesId . ')';
        } else {
            $ref = $properties['ref'];
        }

        $model->update([
            'osm_type' => $properties['osm_type'],
            'osm_id' => $properties['osm_id'],
            'ref' => $ref,
            'score' => $properties['score'],
            'geometry' => $geometry,
        ]);
    }
}
