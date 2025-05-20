<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncClubHikingRouteRelationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $modelType;

    protected ?int $modelId;

    /**
     * Create a new job instance.
     *
     * @param  string|null  $modelType  Type of model to process ('Club', 'HikingRoute', or null for all clubs)
     * @param  int|null  $modelId  ID of the specific model (if type is specified)
     * @return void
     */
    public function __construct(?string $modelType = null, ?int $modelId = null)
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[JOB START] SyncClubHikingRouteRelationJob processing: Type={$this->modelType}, ID={$this->modelId}");

        try {
            if ($this->modelType === 'Club') {
                $club = Club::find($this->modelId);
                if ($club) {
                    $this->syncClub($club);
                } else {
                    Log::error("Club with ID {$this->modelId} not found.");
                }
            } elseif ($this->modelType === 'HikingRoute') {
                $hikingRoute = HikingRoute::find($this->modelId);
                if ($hikingRoute) {
                    $this->syncHikingRoute($hikingRoute);
                } else {
                    Log::error("HikingRoute with ID {$this->modelId} not found.");
                }
            } else {
                // If no specific model/type, process all clubs (default behavior)
                Log::info('Processing all clubs.');
                Club::chunk(100, function ($clubs) {
                    foreach ($clubs as $club) {
                        $this->syncClub($club);
                    }
                });
            }
            Log::info("[JOB END] SyncClubHikingRouteRelationJob finished successfully for: Type={$this->modelType}, ID={$this->modelId}");
        } catch (Throwable $e) {
            Log::error("[JOB FAILED] SyncClubHikingRouteRelationJob failed for: Type={$this->modelType}, ID={$this->modelId}. Error: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Sync a specific club with its corresponding hiking routes based on CAI code.
     */
    private function syncClub(Club $club): void
    {
        $clubName = $club->name;
        $clubCode = $club->cai_code;
        Log::info("Syncing Club ID: {$club->id} ({$clubName}) with code: {$clubCode}");

        try {
            $hikingRoutes = HikingRoute::where('osmfeatures_data->properties->source_ref', 'like', '%'.$clubCode.'%')->get();

            if ($hikingRoutes->isNotEmpty()) {
                $hikingRoutesId = $hikingRoutes->pluck('id')->toArray();
                $club->hikingRoutes()->sync($hikingRoutesId);
                Log::info("Synced Club ID: {$club->id} ({$clubName}) with ".count($hikingRoutesId).' routes.');
            } else {
                $club->hikingRoutes()->detach();
                Log::info("No routes found for Club ID: {$club->id} ({$clubName}). Detached existing routes.");
            }
        } catch (Throwable $e) {
            Log::error("Error during syncClub for Club ID {$club->id}: ".$e->getMessage());
        }
    }

    /**
     * Sync a specific hiking route with its corresponding clubs based on source_ref.
     */
    private function syncHikingRoute(HikingRoute $hikingRoute): void
    {
        Log::info("Syncing Hiking Route ID: {$hikingRoute->id}");

        $sourceRef = $hikingRoute->osmfeatures_data['properties']['source_ref'] ?? null;

        if (! $sourceRef || ! is_string($sourceRef)) {
            Log::warning("Hiking route ID: {$hikingRoute->id} has no valid source_ref property. Detaching any existing clubs.");
            $hikingRoute->clubs()->detach();

            return;
        }

        try {
            // Split source_ref by semicolon and process each code
            $sourceRefCodes = explode(';', $sourceRef);
            $clubIds = [];

            foreach ($sourceRefCodes as $code) {
                $trimmedCode = trim($code);
                if (! empty($trimmedCode)) {
                    // Find clubs with this exact code
                    $clubs = Club::where('cai_code', $trimmedCode)->get();
                    if ($clubs->isNotEmpty()) {
                        $clubIds = array_merge($clubIds, $clubs->pluck('id')->toArray());
                    } else {
                        Log::warning("No club found for CAI code '{$trimmedCode}' referenced by Hiking Route ID: {$hikingRoute->id}");
                    }
                }
            }

            // Remove duplicates
            $clubIds = array_unique($clubIds);

            if (! empty($clubIds)) {
                $hikingRoute->clubs()->sync($clubIds);
                Log::info("Synced Hiking Route ID: {$hikingRoute->id} with ".count($clubIds)." clubs (from codes: {$sourceRef})");
            } else {
                $hikingRoute->clubs()->detach();
                Log::info("No clubs found for Hiking Route ID: {$hikingRoute->id} (from codes: {$sourceRef}). Detached existing clubs.");
            }
        } catch (Throwable $e) {
            Log::error("Error during syncHikingRoute for Route ID {$hikingRoute->id}: ".$e->getMessage());
            // Log error but allow job to continue (handled by main catch)
        }
    }
}
