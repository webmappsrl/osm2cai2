<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Wm\WmPackage\Jobs\Pbf\GenerateOptimizedPBFByZoomJob;

class GeneratePbfAfterOsmfeaturesSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 1800; // 30 minuti

    public function __construct(protected string $modelName) {}

    public function handle()
    {
        $syncKey = "osmfeatures_sync:modified:{$this->modelName}";
        
        // Recupera tutti gli app_id che hanno hiking routes modificate
        $appIds = Redis::smembers("{$syncKey}:apps");
        
        if (empty($appIds)) {
            Log::info("No modified hiking routes found for PBF generation after osmfeatures sync");
            return;
        }

        $totalTracks = 0;
        $jobsDispatched = 0;

        foreach ($appIds as $appId) {
            $appKey = "{$syncKey}:app:{$appId}";
            $hikingRouteIds = Redis::smembers($appKey);
            
            if (empty($hikingRouteIds)) {
                continue;
            }

            // Verifica che le hiking routes esistano e abbiano geometria valida
            $validIds = HikingRoute::whereIn('id', $hikingRouteIds)
                ->whereNotNull('geometry')
                ->where('app_id', $appId)
                ->pluck('id')
                ->toArray();

            if (empty($validIds)) {
                Log::warning("No valid hiking routes found for app_id {$appId}");
                Redis::del($appKey);
                continue;
            }

            // Dispatch del job ottimizzato per ogni livello di zoom (come nell'observer: 13-5)
            $startZoom = 13; // Come nell'observer HikingRouteObserver
            $minZoom = 5;    // Come nell'observer HikingRouteObserver
            
            for ($zoom = $startZoom; $zoom >= $minZoom; $zoom--) {
                GenerateOptimizedPBFByZoomJob::dispatch(
                    $appId,
                    $zoom,
                    false, // no_pbf_layer
                    $validIds // trackIds delle hiking routes modificate
                )->onConnection('redis')->onQueue('pbf');
                
                $jobsDispatched++;
            }

            $totalTracks += count($validIds);
            
            Log::info("Dispatched optimized PBF generation for app_id {$appId}", [
                'app_id' => $appId,
                'hiking_route_count' => count($validIds),
                'zoom_levels' => "{$startZoom} → {$minZoom}",
                'jobs_dispatched' => ($startZoom - $minZoom + 1),
            ]);

            // Pulisci la cache per questa app
            Redis::del($appKey);
        }

        // Pulisci la cache degli app_id
        Redis::del("{$syncKey}:apps");

        Log::info("Generated optimized PBF jobs after osmfeatures sync", [
            'total_hiking_routes' => $totalTracks,
            'apps_processed' => count($appIds),
            'total_jobs_dispatched' => $jobsDispatched,
        ]);
    }
}
