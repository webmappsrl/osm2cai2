<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CalculateTerritorialUnitGeometricRelationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:calculate-territorial-unit-geometric-relations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command calculates the geometric relations between territorial units (regions, provinces, areas, sectors)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting geometric relations calculation...');
        Log::info('Starting geometric relations calculation');

        $regions = Region::all();
        $this->info('Processing ' . $regions->count() . ' regions...');
        foreach ($regions as $region) {
            $this->line("Processing region: {$region->name}");
            $provincesForTheRegion = $region->getIntersections(new Province());
            $this->line("Found {$provincesForTheRegion->count()} intersecting provinces");
            Log::info("Processing region {$region->name} with {$provincesForTheRegion->count()} intersecting provinces");

            $provincesForTheRegion->each(function ($province) use ($region) {
                $this->line("- Linking province {$province->name} to region {$region->name}");
                $province->region_id = $region->id;
                $province->save();
            });
        }

        $provinces = Province::all();
        $this->info('Processing ' . $provinces->count() . ' provinces...');
        foreach ($provinces as $province) {
            $this->line("Processing province: {$province->name}");
            $areasForTheProvince = $province->getIntersections(new Area());
            $this->line("Found {$areasForTheProvince->count()} intersecting areas");
            Log::info("Processing province {$province->name} with {$areasForTheProvince->count()} intersecting areas");

            $areasForTheProvince->each(function ($area) use ($province) {
                $this->line("- Linking area {$area->name} to province {$province->name}");
                $area->province_id = $province->id;
                $area->save();
            });
        }

        $areas = Area::all();
        $this->info('Processing ' . $areas->count() . ' areas...');
        foreach ($areas as $area) {
            $this->line("Processing area: {$area->name}");
            $sectorsForTheArea = $area->getIntersections(new Sector());
            $this->line("Found {$sectorsForTheArea->count()} intersecting sectors");
            Log::info("Processing area {$area->name} with {$sectorsForTheArea->count()} intersecting sectors");

            $sectorsForTheArea->each(function ($sector) use ($area) {
                $this->line("- Linking sector {$sector->name} to area {$area->name}");
                $sector->area_id = $area->id;
                $sector->save();
            });
        }

        $this->info('Geometric relations calculation completed successfully');
        Log::info('Geometric relations calculation completed');
    }
}
