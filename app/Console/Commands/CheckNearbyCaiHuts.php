<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;

class CheckNearbyCaiHuts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:check-nearby-huts {hiking_route_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check nearby cai huts for a hiking route';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $buffer = config('osm2cai.hiking_route_buffer');

        $hikingRoute = HikingRoute::find($this->argument('hiking_route_id'));

        $this->checkNearbyHuts($hikingRoute, $buffer);
    }

    protected function checkNearbyHuts(HikingRoute $hikingRoute, $buffer)
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

        //only save if there is a change so the event observer in the hiking route model will not be triggered [app\Models\HikingRoute line 100]
        if ($currentHuts !== $nearbyHutsIds) {
            $hikingRoute->nearby_cai_huts = json_encode($nearbyHutsIds);
            $hikingRoute->save();
        }
    }
}
