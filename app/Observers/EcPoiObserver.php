<?php

namespace App\Observers;

use App\Jobs\CalculateIntersectionsJob;
use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Jobs\CheckNearbyHutsJob;
use App\Models\Club;
use App\Models\MountainGroups;
use Wm\WmPackage\Models\EcPoi;
use Wm\WmPackage\Observers\EcPoiObserver as WmEcPoiObserver;

class EcPoiObserver extends WmEcPoiObserver
{
    /**
     * Handle the EcPoi "saved" event.
     *
     * @param  EcPoi  $ecPoi
     * @return void
     */
    public function saved($ecPoi)
    {
        parent::saved($ecPoi);

        // Calcola le intersezioni se la geometria è cambiata
        if ($ecPoi->isDirty('geometry')) {
            CalculateIntersectionsJob::dispatch($ecPoi, Club::class)->onQueue('geometric-computations');
            CalculateIntersectionsJob::dispatch($ecPoi, MountainGroups::class)->onQueue('geometric-computations');
            CheckNearbyHikingRoutesJob::dispatch($ecPoi, config('osm2cai.hiking_route_buffer'))->onQueue('geometric-computations');
            CheckNearbyHutsJob::dispatch($ecPoi, config('osm2cai.cai_hut_buffer'))->onQueue('geometric-computations');
        }
    }

    /**
     * Handle the EcPoi "deleting" event.
     *
     * @param  \App\Models\EcPoi  $ecPoi
     * @return void
     */
    public function deleting(EcPoi $ecPoi)
    {
        // Pulisce le relazioni prima della cancellazione
        $ecPoi->cleanRelations();

        // Chiama il metodo deleting del parent per la logica del package
        parent::deleting($ecPoi);
    }
}
