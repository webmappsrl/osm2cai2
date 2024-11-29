<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateIntersectionsJob;
use App\Models\Region;
use App\Models\HikingRoute;
use Illuminate\Console\Command;
use App\Services\IntersectionService;

class CalculateRegionHikingRoutesIntersection extends Command
{
    protected $signature = 'osm2cai:calculate-region-hiking-routes-intersection';

    protected $description = 'Calculate the hiking routes that intersect each region';

    protected $intersectionService;

    public function __construct(IntersectionService $intersectionService)
    {
        parent::__construct();
        $this->intersectionService = $intersectionService;
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
