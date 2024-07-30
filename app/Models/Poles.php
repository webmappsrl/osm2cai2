<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class Poles extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesImportableTrait, OsmfeaturesSyncableTrait;

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
        if (!$model) {
            throw WmOsmfeaturesException::modelNotFound($osmfeaturesId);
        }

        $osmfeaturesData = json_decode($model->osmfeatures_data, true);

        //format the geometry
        if ($osmfeaturesData['geometry']) {
            $geometry = DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('" . json_encode($osmfeaturesData['geometry']) . "'))")[0]->st_astext;
        } else {
            Log::info('No geometry found for Pole ' . $osmfeaturesId);
            $geometry = null;
        }

        if ($osmfeaturesData['properties']['ref'] === null) {
            Log::info('No ref found for Pole ' . $osmfeaturesId);
            $ref = 'noname(' . $osmfeaturesId . ')';
        } else {
            $ref = $osmfeaturesData['properties']['ref'];
        }

        $model->update([
            'osm_type' => $osmfeaturesData['properties']['osm_type'],
            'osm_id' => $osmfeaturesData['properties']['osm_id'],
            'ref' => $ref,
            'geometry' => $geometry,
        ]);
    }
}
