<?php

namespace App\Console\Commands;

use App\Jobs\CalculateIntersectionsJob;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Console\Command;

class CalculateRegionHikingRoutesIntersection extends Command
{
    protected $signature = 'osm2cai:calculate-region-hiking-routes-intersection';

    protected $description = 'Calculate the hiking routes that intersect each region and populate the pivot table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Dispatching recalculate intersections jobs...');
        try {
            $regions = Region::all();

            $bar = $this->output->createProgressBar($regions->count());
            $bar->start();

            foreach ($regions as $region) {
                if (! $region->geometry || empty($region->geometry) || ! isset($region->geometry)) {
                    $this->error('Region ' . $region->id . ' has no geometry');
                    continue;
                }
                CalculateIntersectionsJob::dispatch($region, HikingRoute::class)->onQueue('geometric-computations');
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
