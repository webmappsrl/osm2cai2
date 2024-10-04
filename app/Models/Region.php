<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\RecalculateIntersections;
use Illuminate\Database\Eloquent\Model;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class Region extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesSyncableTrait, OsmfeaturesGeometryUpdateTrait;

    protected $fillable = ['osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'geometry', 'name', 'num_expected', 'hiking_routes_intersecting'];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
        'hiking_routes_intersecting' => 'array',
    ];

    protected static function booted()
    {
        static::updated(function ($region) {
            if ($region->isDirty('geometry')) {
                RecalculateIntersections::dispatch($region);
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
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
        return ['admin_level' => 4];
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
            Log::channel('wm-osmfeatures')->info('No data found for Region ' . $osmfeaturesId);
            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update the name if necessary
        $newName = $osmfeaturesData['properties']['name'] ?? null;
        if ($newName !== $model->name) {
            $updateData['name'] = $newName;
            Log::channel('wm-osmfeatures')->info('Name updated for Region ' . $osmfeaturesId);
        }

        // Execute the update only if there are data to update
        if (!empty($updateData)) {
            $model->update($updateData);
        }
    }
}
