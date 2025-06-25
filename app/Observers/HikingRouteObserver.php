<?php

namespace App\Observers;

use App\Jobs\ComputeTdhJob;
use App\Jobs\SyncClubHikingRouteRelationJob;
use App\Models\HikingRoute;
use App\Models\Layer;

class HikingRouteObserver
{
    /**
     * Handle the HikingRoute "created" event.
     */
    public function created(HikingRoute $hikingRoute): void
    {
        SyncClubHikingRouteRelationJob::dispatch('HikingRoute', $hikingRoute->id);
    }

    /**
     * Handle the HikingRoute "saving" event.
     */
    public function saving(HikingRoute $hikingRoute): void
    {
        if ($hikingRoute->isDirty('osmfeatures_data.properties.ref') && $hikingRoute->isDirty('osmfeatures_data.properties.from') && $hikingRoute->isDirty('osmfeatures_data.properties.to')) {
            $hikingRoute->name = $hikingRoute->osmfeatures_data['properties']['ref'].' - '.$hikingRoute->osmfeatures_data['properties']['from'].' - '.$hikingRoute->osmfeatures_data['properties']['to'];
            $hikingRoute->saveQuietly();
        }

        // todo: insert all properties
        if ($hikingRoute->isDirty('geometry')) {
            // This logic was previously in the 'saved' event.
            // Moved to 'saving' to ensure it runs before the 'updated' event logic.
            if ($hikingRoute->exists) { // To mimic 'updated' event behavior
                if ($hikingRoute->osm2cai_status == 4 && app()->environment('production')) {
                    ComputeTdhJob::dispatch($hikingRoute->id);
                }
            }
            $hikingRoute->dispatchGeometricComputationsJobs('geometric-computations');
        }
        if ($hikingRoute->isDirty('osm2cai_status')) {
            $this->updateLayerAssociations($hikingRoute);
        }
    }

    /**
     * Aggiorna le associazioni della hiking route ai layer in base al cambio di osm2cai_status
     */
    private function updateLayerAssociations(HikingRoute $hikingRoute): void
    {
        // Trova il layer corrispondente al nuovo stato
        $osm2caiStatusLayer = Layer::where('app_id', $hikingRoute->app_id)
            ->where('properties->osm2cai_status', $hikingRoute->osm2cai_status)
            ->first();

        // Ottieni il valore precedente dello stato per trovare il layer precedente
        $oldStatus = $hikingRoute->getOriginal('osm2cai_status');
        $previousStatusLayer = null;

        if ($oldStatus !== null) {
            $previousStatusLayer = Layer::where('app_id', $hikingRoute->app_id)
                ->where('properties->osm2cai_status', $oldStatus)
                ->first();
        }

        // Disassocia dal layer precedente se esiste
        if ($previousStatusLayer) {
            $hikingRoute->layers()->detach($previousStatusLayer->id);
        }

        // Associa al nuovo layer se esiste
        if ($osm2caiStatusLayer && ! $hikingRoute->layers()->where('layer_id', $osm2caiStatusLayer->id)->exists()) {
            $hikingRoute->layers()->attach($osm2caiStatusLayer->id);
        }
    }

    /**
     * Handle the HikingRoute "deleting" event.
     */
    public function deleting(HikingRoute $hikingRoute): void
    {
        $hikingRoute->cleanRelations();
        $hikingRoute->clearMediaCollection('feature_image');
    }
}
