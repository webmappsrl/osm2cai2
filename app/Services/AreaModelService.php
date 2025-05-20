<?php

namespace App\Services;

use App\Models\Area;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class AreaModelService
{
    /**
     * Compute area geometry by merging its sectors geometries
     *
     * @return mixed
     */
    public function computeGeometryBySectors(Area $area)
    {
        $sectorIds = $area->children->pluck('id')->toArray();

        $result = DB::table('sectors')
            ->selectRaw('ST_Union(ST_force2d(geometry)) as geometry')
            ->whereIn('id', $sectorIds)
            ->first();

        return $result->geometry;
    }

    /**
     * Compute and save area geometry based on its sectors
     *
     * @throws Exception|Throwable
     */
    public function computeAndSaveGeometryBySectors(Area $area): void
    {
        try {
            $area->geometry = $this->computeGeometryBySectors($area);
            $area->save(); // triggering intersection computation with hiking routes (because geometry is changed)
        } catch (Exception|Throwable $e) {
            throw $e;
        }
    }
}
