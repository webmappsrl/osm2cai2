<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait to get elements in a given buffer distance (m) from the given model
 */
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
        if (! $this->geometry || empty($this->geometry)) {
            Log::error('Model has no geometry', ['model' => $this]);

            return collect();
        }

        $nearbyIds = DB::table($model->getTable())
            ->select('id')
            ->whereRaw('ST_DWithin(geometry::geography, (SELECT geometry::geography FROM '.$this->getTable().' WHERE id = ?), ?)', [$this->id, $buffer])
            ->pluck('id');

        return $model::whereIn('id', $nearbyIds)->get();
    }
}
