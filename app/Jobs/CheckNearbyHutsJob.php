<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
        if (! $hikingRoute->geometry) {
            Log::warning("Hiking route {$hikingRoute->id} has no geometry");

            return;
        }
        //geometry casted to geography because more accurate for distance calculations
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
            'routeId' => $hikingRoute->id,
            'buffer' => $buffer,
        ]);

        $nearbyHutsIds = array_map(
            function ($hut) {
                return $hut->id;
            },
            $nearbyHutsIds
        );

        $currentHuts = json_decode($hikingRoute->nearby_cai_huts, true) ?: [];
        sort($currentHuts);
        sort($nearbyHutsIds);

        //only save if there is a change so the event observer in the hiking route model will not be triggered
        if ($currentHuts !== $nearbyHutsIds) {
            $hikingRoute->nearby_cai_huts = json_encode($nearbyHutsIds);
            $hikingRoute->save();
        }
    }
}
