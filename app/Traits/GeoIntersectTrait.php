<?php

namespace App\Traits;

use App\Models\EcPoi;
use App\Models\CaiHuts;
use App\Models\Section;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait GeoIntersectTrait
{

    /**
     * Get hiking routes that intersect with the given model
     * 
     * @return Collection
     */
    public function getHikingRoutesIntersecting(): Collection
    {
        $model = $this;

        $intersectingHikingRouteIds = DB::table('hiking_routes')
            ->select('id')
            ->whereRaw("ST_Intersects(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('id');


        return HikingRoute::whereIn('id', $intersectingHikingRouteIds)->get();
    }

    /**
     * Get hiking routes in a given buffer distance (m) from the given model
     * 
     * @param int $buffer
     * 
     * @return Collection
     */
    public function getHikingRoutesInBuffer(int $buffer): Collection
    {
        $model = $this;

        $hikingRouteIds = DB::table('hiking_routes')
            ->select('id')
            ->whereRaw("ST_DWithin(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?), ?)", [$model->id, $buffer])
            ->pluck('id');

        return HikingRoute::whereIn('id', $hikingRouteIds)->get();
    }

    /**
     * Get huts that intersect with the given model
     * 
     * @return Collection
     */
    public function getHutsIntersecting(): Collection
    {
        $model = $this;

        $intersectingHutsIds = DB::table('cai_huts')
            ->select('id')
            ->whereRaw("ST_Intersects(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('id');

        return CaiHuts::whereIn('id', $intersectingHutsIds)->get();
    }

    /**
     * Get pois that intersect with the given model
     * 
     * @return Collection
     */
    public function getPoisIntersecting(): Collection
    {
        $model = $this;
        $intersectingPoisIds = DB::table('ec_pois')
            ->select('id')
            ->whereRaw("ST_Intersects(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('id');

        return EcPoi::whereIn('id', $intersectingPoisIds)->get();
    }

    /**
     * Get pois in buffer distance (m) from the given model
     * 
     * @param int $buffer
     * 
     * @return collection
     */
    public function getPoisInBuffer(int $buffer): Collection
    {
        $model = $this;
        $poiIds = DB::table('ec_pois')
            ->select('id')
            ->whereRaw("ST_DWithin(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?), ?)", [$model->id, $buffer])
            ->pluck('id');

        return EcPoi::whereIn('id', $poiIds)->get();
    }

    /**
     * Get Mountain groups that intersect with the given model
     * 
     * @return Collection
     */
    public function getMountainGroupsIntersecting(): Collection
    {
        $model = $this;
        $intersectingMountainGroupsIds = DB::table('mountain_groups')
            ->select('id')
            ->whereRaw("ST_Intersects(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('id');

        return MountainGroups::whereIn('id', $intersectingMountainGroupsIds)->get();
    }


    /**
     * Get the municipality boundaries that intersect with the given model
     * 
     * @return Collection
     */
    public function getMunicipalityIntersecting(): Collection
    {
        $model = $this;
        $intersectingMunicipalitiesIds = DB::table('municipality_boundaries')
            ->select('gid')
            ->whereRaw("ST_Intersects(geom, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('gid');

        $municipality = DB::table('municipality_boundaries')->whereIn('gid', $intersectingMunicipalitiesIds)->get();

        return $municipality;
    }

    /**
     * Get the sections that intersect with the given model
     * 
     * @return Collection
     */
    public function getSectionsIntersecting(): Collection
    {
        $model = $this;
        $intersectingSectionsIds = DB::table('sections')
            ->select('id')
            ->whereRaw("ST_Intersects(geometry, (SELECT geometry FROM " . $model->getTable() . " WHERE id = ?))", [$model->id])
            ->pluck('id');

        return Section::whereIn('id', $intersectingSectionsIds)->get();
    }
    /**
     * Get the geometry of the given model
     * 
     * @return array
     */

    public function getGeometry(): array
    {
        $model = $this;
        $geometryQuery = 'SELECT ST_AsGeoJSON(geometry) as geom FROM ' . $model->getTable() . ' WHERE id = ' . $model->id;
        $geom = DB::select($geometryQuery)[0]->geom;


        return json_decode($geom, true);
    }
}