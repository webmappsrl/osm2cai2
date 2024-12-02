<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateIntersectionsJob;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Console\Command;

class CalculateRegionHikingRoutesIntersection extends Command
{
    protected $signature = 'osm2cai:calculate-region-hiking-routes-intersection';

    protected $description = 'Calculate the hiking routes that intersect each region';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Dispatching recalculate intersections jobs...');
        try {
            $regions = Region::all();
            $hikingRoutes = HikingRoute::all();
            $totalItems = $regions->count() + $hikingRoutes->count();

            $bar = $this->output->createProgressBar($totalItems);
            $bar->start();

            foreach ($regions as $region) {
                RecalculateIntersectionsJob::dispatch($region, HikingRoute::class);
                $bar->advance();
            }

            foreach ($hikingRoutes as $hikingRoute) {
                RecalculateIntersectionsJob::dispatch($hikingRoute, Region::class);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
        $this->info('Recalculate intersections jobs dispatched successfully.');
    }
}
