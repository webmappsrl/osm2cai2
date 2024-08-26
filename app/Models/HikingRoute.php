<?php

namespace App\Models;

use App\Traits\OsmFeaturesIdProcessor;
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
        'osmfeatures_data' => 'array',
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
            $geometry = DB::select(
                <<<SQL
                    SELECT ST_AsText(ST_GeomFromGeoJSON(:geojson))
SQL,
                ['geojson' => json_encode($osmfeaturesData['geometry'])]
            )[0]->st_astext;
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry found for HikingRoute ' . $osmfeaturesId);
            $geometry = null;
        }

        $model->update([
            'geometry' => $geometry,
        ]);
    }

    /**
     * Get Data for nova Link Card
     * 
     * @return array
     */
    public function getDataForNovaLinksCard()
    {
        $osmId = $this->osmfeatures_data['properties']['osm_id'];
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
}
