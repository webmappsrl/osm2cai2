<?php

namespace App\Console\Commands;

use App\Services\IntersectionService;
use Illuminate\Console\Command;

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
        $this->info('Start calculating intersections...');
        try {
            $this->intersectionService->calculateIntersections();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
        $this->info('Calculating intersections completed successfully.');
    }
}
