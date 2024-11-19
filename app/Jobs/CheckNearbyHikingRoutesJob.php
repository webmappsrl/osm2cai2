<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
            )', [$buffer])
            ->get();

        foreach ($nearbyRoutes as $route) {
            $hr = HikingRoute::find($route->id);
            $currentHuts = json_decode($hr->cai_huts, true) ?: [];
            if (! in_array($this->caiHut->id, $currentHuts)) {
                array_push($currentHuts, $this->caiHut->id);
                $hr->update([
                    'cai_huts' => json_encode($currentHuts),
                ]);
            }
        }
    }
}
