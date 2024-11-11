<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class Municipality extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesSyncableTrait, OsmfeaturesGeometryUpdateTrait;

    protected $fillable = [
        'name',
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
        return 'https://osmfeatures.maphub.it/api/v1/features/admin-areas/';
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
        return ['admin_level' => 8];
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
            Log::channel('wm-osmfeatures')->info('No data found for Municipality ' . $osmfeaturesId);

            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        if ($osmfeaturesData['properties']['name'] !== null && $osmfeaturesData['properties']['name'] !== $model->name) {
            $updateData['name'] = $osmfeaturesData['properties']['name'];
        } else if ($osmfeaturesData['properties']['name'] === null) {
            Log::channel('wm-osmfeatures')->info('No name found for Municipality ' . $osmfeaturesId);
        }

        $model->update($updateData);
    }
}
