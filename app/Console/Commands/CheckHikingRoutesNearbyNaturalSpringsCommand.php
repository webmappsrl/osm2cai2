<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckNearbyHutsJob;
use App\Jobs\CheckNearbyNaturalSpringsJob;

class CheckHikingRoutesNearbyNaturalSpringsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:check-hiking-routes-nearby-natural-springs {id? : The id of the hiking route}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check hiking routes nearby natural springs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $buffer = config('osm2cai2.hiking_route_buffer');

        if ($this->argument('id')) {
            CheckNearbyNaturalSpringsJob::dispatch($this->argument('id'), $buffer);
        } else {
            $hikingRoutes = DB::table('hiking_routes')->select(['id'])->get();
        }

        foreach ($hikingRoutes as $hikingRoute) {
            CheckNearbyNaturalSpringsJob::dispatch($hikingRoute->id, $buffer);
        }
    }
}
