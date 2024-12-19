<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait to get intersections with other models
 */
trait GeoIntersectTrait
{
    /**
     * Get intersections with the given model
     *
     * @param Model $model the element to find intersections with
     *
     * @return Collection
     */
    public function getIntersections(Model $model): Collection
    {
        if (! $this->geometry || empty($this->geometry) || ! isset($this->geometry)) {
            throw new \Exception('Model must have a geometry column');
        }

        try {
            $intersectingIds = DB::table($model->getTable())
                ->select('id')
                ->whereRaw('ST_Intersects(geometry::geography, (SELECT geometry::geography FROM '.$this->getTable().' WHERE id = ?))', [$this->id])
                ->pluck('id');

            return $model::whereIn('id', $intersectingIds)->get();
        } catch (\Exception $e) {
            Log::error('Error getting intersections for model '.$this->getTable().': '.$e->getMessage());
            throw $e;
        }
    }
}
