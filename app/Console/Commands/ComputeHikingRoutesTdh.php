<?php

namespace App\Console\Commands;

use App\Jobs\ComputeTdhJob;
use App\Models\HikingRoute;
use Illuminate\Console\Command;

class ComputeHikingRoutesTdh extends Command
{
    protected $signature = 'osm2cai:compute-hiking-routes-tdh {id?}';

    protected $description = 'Compute the TDH of a hiking route on SDA 4';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $id = $this->argument('id') ?? null;
        if (! $id) {
            $hikingRoutes = HikingRoute::where('osm2cai_status', 4)->get(['id']);
        } else {
            $hikingRoute = HikingRoute::find($id)->with('tdh', 'osmfeatures_data', 'geometry');
            if (! $hikingRoute) {
                $this->error('Hiking route not found');

                return;
            } elseif ($hikingRoute->osm2cai_status !== 4) {
                $this->error('Hiking route has not SDA 4 status');

                return;
            }
            ComputeTdhJob::dispatch($hikingRoute->id);

            return;
        }

        foreach ($hikingRoutes as $hikingRoute) {
            ComputeTdhJob::dispatch($hikingRoute->id);
        }
    }
}
