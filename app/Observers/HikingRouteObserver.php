<?php

namespace App\Observers;

use App\Jobs\ComputeTdhJob;
use App\Jobs\SyncClubHikingRouteRelationJob;
use App\Models\HikingRoute;
use App\Models\Layer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Services\PBFGeneratorService;
use Wm\WmPackage\Services\GeometryComputationService;
use Wm\WmPackage\Jobs\Pbf\GenerateLayerPBFJob;
use Wm\WmPackage\Jobs\Pbf\GeneratePBFJob;

class HikingRouteObserver
{
    /**
     * Handle the HikingRoute "created" event.
     */
    public function created(HikingRoute $hikingRoute): void
    {
        SyncClubHikingRouteRelationJob::dispatch('HikingRoute', $hikingRoute->id);
    }

    public function updatePbfsForHikingRoute(HikingRoute $hikingRoute): void
    {
        $pbfService = app(PBFGeneratorService::class);
        $geometryService = app(GeometryComputationService::class);

        // Genera i tile impattati dalla modifica della traccia
        $impactedTiles = $geometryService->generateImpactedTilesForTrack(
            $hikingRoute,
            13, // startZoom
            5   // minZoom
        );

        if (!empty($impactedTiles)) {
            // Crea i job per ogni tile impattato
            $jobs = [];
            foreach ($impactedTiles as $tile) {
                [$x, $y, $zoom] = $tile;

                // Scegli il tipo di job in base al livello di zoom
                if ($zoom <= $pbfService->getZoomTreshold()) {
                    $jobs[] = new GenerateLayerPBFJob($zoom, $x, $y, $hikingRoute->app_id);
                } else {
                    $jobs[] = new GeneratePBFJob($zoom, $x, $y, $hikingRoute->app_id);
                }
            }

            // Dispatch del batch se ci sono job da eseguire
            if (!empty($jobs)) {
                $batch = \Illuminate\Support\Facades\Bus::batch($jobs)
                    ->name("PBF Regeneration for Track {$hikingRoute->id}: {$hikingRoute->app_id}")
                    ->onConnection('redis')
                    ->onQueue('pbf')
                    ->dispatch();

                Log::info("Batch di rigenerazione PBF avviato per traccia {$hikingRoute->id}: " . count($jobs) . " job");
            }
        }
    }

    /**
     * Handle the HikingRoute "updated" event.
     */
    public function updated(HikingRoute $hikingRoute): void
    {
        Log::info('HikingRouteObserver updated event');
        // Rigenera i tile PBF ottimizzati solo se la geometria è stata modificata
        //if ($hikingRoute->isDirty('geometry')) {}

    }

    /**
     * Handle the HikingRoute "saving" event.
     */
    public function saving(HikingRoute $hikingRoute): void
    {
        Log::info('HikingRouteObserver saving event');
        if ($hikingRoute->isDirty('osmfeatures_data.properties.ref') && $hikingRoute->isDirty('osmfeatures_data.properties.from') && $hikingRoute->isDirty('osmfeatures_data.properties.to')) {
            $hikingRoute->name = $hikingRoute->osmfeatures_data['properties']['ref'] . ' - ' . $hikingRoute->osmfeatures_data['properties']['from'] . ' - ' . $hikingRoute->osmfeatures_data['properties']['to'];
            // Non usare saveQuietly() qui per evitare il loop di eventi
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
            $this->updatePbfsForHikingRoute($hikingRoute);
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

        // Sincronizza le relazioni con i layer usando sync()
        $layerIds = $hikingRoute->layers()->pluck('layers.id')->toArray();
        
        // Rimuovi il layer del vecchio status se esiste
        if ($previousStatusLayer) {
            $layerIds = array_diff($layerIds, [$previousStatusLayer->id]);
        }
        
        // Aggiungi il layer del nuovo status se esiste
        if ($osm2caiStatusLayer) {
            $layerIds[] = $osm2caiStatusLayer->id;
        }
        Log::info('layerIds', $layerIds);
        $hikingRoute->layers()->sync($layerIds);
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
