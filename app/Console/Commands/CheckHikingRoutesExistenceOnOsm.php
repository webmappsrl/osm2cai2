<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use App\Services\OsmService;
use Illuminate\Console\Command;
use App\Jobs\CheckHikingRouteExistenceOnOSM;

class CheckHikingRoutesExistenceOnOsm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:check_hr_existence_on_osm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iterates over all hiking routes to populate deleted_on_osm attribute checking osm2cai api';

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
     *
     * @return int
     */
    public function handle()
    {
        HikingRoute::where('deleted_on_osm', false)->each(function ($hr) {
            $this->info('dispatching job for hiking route ' . $hr->id);
            CheckHikingRouteExistenceOnOSM::dispatch($hr);
        });
        return 0;
    }
}
