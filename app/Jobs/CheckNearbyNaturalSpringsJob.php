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

class CheckNearbyNaturalSpringsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $hikingRouteId;

    protected $buffer;

    public function __construct($hikingRouteId, $buffer)
    {
        $this->hikingRouteId = $hikingRouteId;
        $this->buffer = $buffer;
    }

    public function handle(): void
    {
        try {
            $hikingRoute = HikingRoute::find($this->hikingRouteId);

            if (! $hikingRoute) {
                Log::error('Hiking route not found', ['route_id' => $this->hikingRouteId]);
                throw new \Exception('Hiking route not found');
            }

            if (! $hikingRoute->geometry) {
                Log::error("Hiking route {$hikingRoute->id} has no geometry");
                throw new \Exception("Hiking route {$hikingRoute->id} has no geometry");
            }

            $nearbySprings = DB::select(<<<'SQL'
                SELECT natural_springs.id
                FROM natural_springs, hiking_routes
                WHERE hiking_routes.id = :routeId
                AND ST_DWithin(
                    ST_SetSRID(hiking_routes.geometry, 4326)::geography,
                    natural_springs.geometry,
                    :buffer
                )
            SQL, [
                'routeId' => $this->hikingRouteId,
                'buffer' => $this->buffer,
            ]);

            $nearbySpringsIds = array_map(fn ($spring) => $spring->id, $nearbySprings);
            $currentNearbySpringsIds = is_string($hikingRoute->nearby_natural_springs)
                ? json_decode($hikingRoute->nearby_natural_springs, true)
                : [];
            sort($currentNearbySpringsIds);
            sort($nearbySpringsIds);

            if ($currentNearbySpringsIds !== $nearbySpringsIds) {
                $hikingRoute->update(['nearby_natural_springs' => json_encode($nearbySpringsIds)]);
            }
        } catch (\Throwable $e) {
            Log::error('Error in CheckNearbyNaturalSpringsJob: '.$e->getMessage());
        }
    }
}
