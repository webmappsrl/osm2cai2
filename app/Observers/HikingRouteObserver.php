<?php

namespace App\Observers;

use App\Jobs\ComputeTdhJob;
use App\Jobs\GetTaxonomyWheresFromOsmfeaturesJob;
use App\Jobs\SyncClubHikingRouteRelationJob;
use App\Models\HikingRoute;

class HikingRouteObserver
{
    /**
     * Handle the HikingRoute "created" event.
     *
     * @param  \App\Models\HikingRoute  $hikingRoute
     * @return void
     */
    public function created(HikingRoute $hikingRoute): void
    {
        SyncClubHikingRouteRelationJob::dispatch('HikingRoute', $hikingRoute->id);
        GetTaxonomyWheresFromOsmfeaturesJob::dispatch($hikingRoute)->onQueue('geometric-computations');
    }

    /**
     * Handle the HikingRoute "saving" event.
     *
     * @param  \App\Models\HikingRoute  $hikingRoute
     * @return void
     */
    public function saving(HikingRoute $hikingRoute): void
    {
        if ($hikingRoute->isDirty('osmfeatures_data.properties.ref') && $hikingRoute->isDirty('osmfeatures_data.properties.from') && $hikingRoute->isDirty('osmfeatures_data.properties.to')) {
            $hikingRoute->name = $hikingRoute->osmfeatures_data['properties']['ref'] . ' - ' . $hikingRoute->osmfeatures_data['properties']['from'] . ' - ' . $hikingRoute->osmfeatures_data['properties']['to'];
            $hikingRoute->saveQuietly();
        }

        //todo: insert all properties 
        if ($hikingRoute->isDirty('geometry')) {
            // This logic was previously in the 'saved' event.
            // Moved to 'saving' to ensure it runs before the 'updated' event logic.
            if ($hikingRoute->exists) { // To mimic 'updated' event behavior
                GetTaxonomyWheresFromOsmfeaturesJob::dispatch($hikingRoute)->onQueue('geometric-computations');
                if ($hikingRoute->osm2cai_status == 4 && app()->environment('production')) {
                    ComputeTdhJob::dispatch($hikingRoute->id);
                }
            }
            $hikingRoute->dispatchGeometricComputationsJobs('geometric-computations');
        }
    }

    /**
     * Handle the HikingRoute "deleting" event.
     *
     * @param  \App\Models\HikingRoute  $hikingRoute
     * @return void
     */
    public function deleting(HikingRoute $hikingRoute): void
    {
        $hikingRoute->cleanRelations();
        $hikingRoute->clearMediaCollection('feature_image');
    }
}
