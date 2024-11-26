<?php

namespace App\Models;

use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\User;
use App\Traits\CsvableModelTrait;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;

class Province extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesSyncableTrait, OsmfeaturesGeometryUpdateTrait, SpatialDataTrait, CsvableModelTrait;

    protected $fillable = ['osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'name', 'geometry'];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

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
        return ['admin_level' => 6];
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
            Log::channel('wm-osmfeatures')->info('No data found for Province '.$osmfeaturesId);

            return;
        }

        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update the name if necessary
        $newName = $osmfeaturesData['properties']['name'] ?? null;
        if ($newName !== $model->name) {
            $updateData['name'] = $newName;
            Log::channel('wm-osmfeatures')->info('Name updated for Province '.$osmfeaturesId);
        }

        // Execute the update only if there are data to update
        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class);
    }

    public function sectorsIds(): array
    {
        $result = [];
        foreach ($this->areas as $area) {
            $result = array_unique(array_values(array_merge($result, $area->sectorsIds())));
        }

        return $result;
    }
}
