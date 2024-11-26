<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckNearbyHutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HikingRoute $hikingRoute;

    protected $buffer;

    /**
     * Create a new job instance.
     */
    public function __construct(HikingRoute $hikingRoute, $buffer)
    {
        $this->hikingRoute = $hikingRoute;
        $this->buffer = $buffer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (! $this->hikingRoute->geometry) {
                Log::warning("Hiking route {$this->hikingRoute->id} has no geometry");

                return;
            }

            if ($this->buffer < 0) {
                Log::warning('Buffer distance must be positive');

                return;
            }

            // Query per trovare i rifugi vicini
            $nearbyHutsIds = DB::select(<<<'SQL'
            SELECT cai_huts.id 
            FROM cai_huts, hiking_routes 
            WHERE hiking_routes.id = :routeId 
            AND ST_DWithin(
                hiking_routes.geometry::geography, 
                cai_huts.geometry::geography, 
                :buffer
            )
        SQL, [
                'routeId' => $this->hikingRoute->id,
                'buffer' => $this->buffer,
            ]);

            $nearbyHutsIds = array_map(
                function ($hut) {
                    return $hut->id;
                },
                $nearbyHutsIds
            );

            $currentHuts = json_decode($this->hikingRoute->nearby_cai_huts, true) ?: [];
            sort($currentHuts);
            sort($nearbyHutsIds);

            // Aggiorna solo se ci sono cambiamenti
            if ($currentHuts !== $nearbyHutsIds) {
                $this->hikingRoute->nearby_cai_huts = json_encode($nearbyHutsIds);
                $this->hikingRoute->save();
            }
        } catch (\Exception $e) {
            Log::error('Error executing CheckNearbyHutsJob', [
                'route_id' => $this->hikingRoute->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
