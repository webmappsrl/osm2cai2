<?php

namespace App\Console\Commands;

use App\Models\CaiHut;
use Illuminate\Console\Command;

class CheckNearbyHikingRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:check-nearby-hiking-routes {cai_hut_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check nearby hiking routes for a cai hut';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $caiHutId = $this->argument('cai_hut_id');
        $buffer = config('osm2cai.hiking_route_buffer');

        $caiHut = CaiHut::find($caiHutId);

        $this->checkNearbyHikingRoutes($caiHut, $buffer);
    }

    protected function checkNearbyHikingRoutes(CaiHut $caiHut, $buffer)
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
            if (! in_array($caiHut->id, $currentHuts)) {
                array_push($currentHuts, $caiHut->id);
                $hr->update([
                    'cai_huts' => json_encode($currentHuts),
                ]);
            }
        }
    }
}
