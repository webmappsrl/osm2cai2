<?php

namespace App\Models;

use App\Models\User;
use App\Traits\GeoBufferTrait;
use App\Traits\TagsMappingTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class EcPoi extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, TagsMappingTrait, OsmfeaturesImportableTrait, GeoIntersectTrait, GeoBufferTrait;

    protected $fillable = [
        'name',
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
        'type',
        'score',
        'user_id',
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
            Log::channel('wm-osmfeatures')->info('No data found for Ec Poi ' . $osmfeaturesId);

            return;
        }

        //format the geometry
        if ($osmfeaturesData['geometry']) {
            $geometry = DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('" . json_encode($osmfeaturesData['geometry']) . "'))")[0]->st_astext;
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry found for Ec Poi ' . $osmfeaturesId);
            $geometry = null;
        }

        if ($osmfeaturesData['properties']['name'] === null) {
            Log::channel('wm-osmfeatures')->info('No name found for Ec Poi ' . $osmfeaturesId);
            $name = null;
        } else {
            $name = $osmfeaturesData['properties']['name'];
        }

        $model->update([
            'name' => $name,
            'geometry' => $geometry,
            'score' => $osmfeaturesData['properties']['score'],
            'type' => $model->getTagsMapping(),
        ]);
    }

    public function User()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
