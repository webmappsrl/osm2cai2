<?php

namespace App\Models;

use App\Jobs\CalculateIntersectionsJob;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;

class Province extends Model implements OsmfeaturesSyncableInterface
{
    use CsvableModelTrait, HasFactory, IntersectingRouteStats, OsmfeaturesGeometryUpdateTrait, OsmfeaturesSyncableTrait, SallableTrait, SpatialDataTrait;

    protected $fillable = ['osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'osmfeatures_exists', 'name', 'geometry'];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
        'osmfeatures_exists' => 'boolean',
    ];

    protected static function booted()
    {
        static::saved(function ($province) {
            if ($province->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($province, HikingRoute::class)->onQueue('geometric-computations');
            }
        });
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
            Log::channel('wm-osmfeatures')->info('No data found for Province ' . $osmfeaturesId);

            return;
        }

        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update the name if necessary
        $newName = $osmfeaturesData['properties']['name'] ?? null;
        if ($newName !== $model->name) {
            $updateData['name'] = $newName;
            Log::channel('wm-osmfeatures')->info('Name updated for Province ' . $osmfeaturesId);
        }

        // Execute the update only if there are data to update
        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
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

    /**
     * Alias
     */
    public function children()
    {
        return $this->areas();
    }

    public function childrenIds()
    {
        return $this->areasIds();
    }

    public function areasIds(): array
    {
        return $this->areas->pluck('id')->toArray();
    }

    /**
     * Alias
     */
    public function parent()
    {
        return $this->region();
    }

    public function sectorsIds(): array
    {
        $result = [];
        foreach ($this->areas as $area) {
            $result = array_unique(array_values(array_merge($result, $area->sectorsIds())));
        }

        return $result;
    }

    /**
     * Scope a query to only include provinces owned by a certain user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Model\User  $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, User $user)
    {
        // Verify region
        if ($user->region) {
            $query->whereHas('region', function ($q) use ($user) {
                $q->where('id', $user->region->id);
            });
        }

        // Verify provinces
        if ($user->provinces->isNotEmpty()) {
            $query->orWhereIn('id', $user->provinces->pluck('id'));
        }

        return $query;
    }
}
