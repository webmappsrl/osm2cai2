<?php

namespace App\Traits;

use App\Models\CaiHuts;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait GeoBufferTrait
{
    /**
     * Get elements in a given buffer distance (m) from the given model
     * 
     * @param Model $model the element to find in buffer
     * @param int $buffer buffer distance in meters
     * @return Collection
     */
    public function getElementsInBuffer(Model $model, int $buffer): Collection
    {
        $nearbyIds = DB::table($model->getTable())
            ->select('id')
            ->whereRaw('ST_DWithin(geometry::geography, (SELECT geometry::geography FROM ' . $this->getTable() . ' WHERE id = ?), ?)', [$this->id, $buffer])
            ->pluck('id');

        return $model::whereIn('id', $nearbyIds)->get();
    }
}
