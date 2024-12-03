<?php

namespace App\Jobs;

use App\Models\CaiHut;
use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckNearbyHikingRoutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected CaiHut $caiHut;

    protected $buffer;

    /**
     * Create a new job instance.
     */
    public function __construct(CaiHut $caiHut, $buffer)
    {
        $this->caiHut = $caiHut;
        $this->buffer = $buffer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nearbyRoutes = HikingRoute::select('id', 'geometry', 'cai_huts') //geometry casted to geography because more accurate for distance calculations
            ->whereRaw('ST_DWithin(
                hiking_routes.geometry::geography,
                cai_huts.geometry::geography, 
                ?
            )', [$this->buffer])
            ->get();

        foreach ($nearbyRoutes as $route) {
            $hr = HikingRoute::find($route->id);
            $currentHuts = json_decode($hr->nearby_cai_huts, true) ?: [];
            if (! in_array($this->caiHut->id, $currentHuts)) {
                array_push($currentHuts, $this->caiHut->id);
                $hr->update([
                    'nearby_cai_huts' => json_encode($currentHuts),
                ]);
            }
        }
    }
}
