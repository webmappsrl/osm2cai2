<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Attributes\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckNearbyNaturalSpringsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $hikingRouteId;
    protected $buffer;

    /**
     * Create a new job instance.
     */
    public function __construct($hikingRouteId, $buffer)
    {
        $this->hikingRouteId = $hikingRouteId;
        $this->buffer = $buffer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $hikingRoute = DB::table('hiking_routes')->select(['id', 'geometry', 'nearby_natural_springs'])->where('id', $this->hikingRouteId)->first();

        if (! $hikingRoute) {
            throw new \Exception('Hiking route not found');
        }

        if (! $hikingRoute->geometry) {
            throw new \Exception('Hiking route geometry not found');
        }

        $nearbySprings = DB::select("SELECT natural_springs.id 
                                        FROM natural_springs, hiking_routes 
                                        WHERE hiking_routes.id = :routeId 
                                        AND ST_DWithin(ST_SetSRID(hiking_routes.geometry, 4326)::geography, natural_springs.geometry, :buffer)", [
            'routeId' => $this->hikingRouteId,
            'buffer' => $this->buffer
        ]);

        $nearbySpringsIds = array_map(function ($spring) {
            return $spring->id;
        }, $nearbySprings);

        $currentNearbySpringsIds = is_string($hikingRoute->nearby_natural_springs) ? json_decode($hikingRoute->nearby_natural_springs, true) : [];
        sort($currentNearbySpringsIds);
        sort($nearbySpringsIds);

        if ($currentNearbySpringsIds !== $nearbySpringsIds) {
            $hikingRoute->nearby_natural_springs = $nearbySpringsIds;
            $hikingRoute->save();
        }
    }
}
