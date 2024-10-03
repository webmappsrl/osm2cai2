<?php

namespace App\Console\Commands;

use App\Services\IntersectionService;
use Illuminate\Console\Command;

class CalculateRegionHikingRoutesIntersection extends Command
{
    protected $signature = 'osm2cai:calculate-region-hiking-routes-intersection';
    protected $description = 'Calcola le hiking routes che intersecano ogni regione';

    protected $intersectionService;

    public function __construct(IntersectionService $intersectionService)
    {
        parent::__construct();
        $this->intersectionService = $intersectionService;
    }

    public function handle()
    {
        $this->info('Inizio calcolo delle intersezioni...');
        $this->intersectionService->calculateIntersections();
        $this->info('Calcolo delle intersezioni completato con successo.');
    }
}
