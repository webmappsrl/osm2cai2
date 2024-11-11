<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;

class ComputeHikingRoutesTdh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:compute-hiking-routes-tdh {id?}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute the TDH of a hiking route on SDA 4';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id') ?? null;
        if (!$id) {
            $hikingRoutes = HikingRoute::where('osm2cai_status', 4)->get(['id', 'tdh', 'osmfeatures_data', 'geometry']);
        } else {
            $hikingRoute = HikingRoute::find($id)->with('tdh', 'osmfeatures_data', 'geometry');
            if (!$hikingRoute) {
                $this->error('Hiking route not found');
                return;
            }
        }

        foreach ($hikingRoutes as $hikingRoute) {
            $newTdh = $hikingRoute->computeTdh();
            if ($newTdh !== $hikingRoute->tdh) {
                $hikingRoute->tdh = $newTdh;
                $hikingRoute->save();
            }
        }
    }
}
