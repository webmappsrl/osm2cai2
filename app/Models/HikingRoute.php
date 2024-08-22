<?php

namespace App\Models;

use App\Traits\TagsMappingTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class HikingRoute extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory;
    use OsmfeaturesImportableTrait;
    use OsmfeaturesSyncableTrait;
    use TagsMappingTrait;

    protected $fillable = [
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
    ];

    // Metodo magico per ottenere valori dinamici
    public function __get($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return parent::__get($key);
        }
        $osmfeaturesData = json_decode($this->osmfeatures_data, true);

        if (isset($osmfeaturesData['properties'][$key])) {
            return $osmfeaturesData['properties'][$key];
        }

        return parent::__get($key);
    }

    // Metodo magico per impostare valori dinamici
    public function __set($key, $value)
    {
        if (array_key_exists($key, $this->attributes)) {
            parent::__set($key, $value);
        } else {
            $data = $this->osmfeatures_data;
            $data['properties'][$key] = $value;
            $this->attributes['osmfeatures_data'] = json_encode($data);
        }
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