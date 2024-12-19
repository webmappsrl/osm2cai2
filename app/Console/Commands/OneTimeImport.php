<?php

namespace App\Console\Commands;

use App\Console\Commands\AssociateClubsToHikingRoutesCommand;
use App\Console\Commands\CalculateRegionHikingRoutesIntersection;
use App\Console\Commands\CheckHikingRoutesNearbyNaturalSpringsCommand;
use App\Console\Commands\CheckNearbyCaiHuts;
use App\Console\Commands\ComputeHikingRoutesTdh;
use App\Console\Commands\ImportMunicipalityDataFromLegacyOsm2cai;
use App\Console\Commands\Osm2caiSetExpectedValuesCommand;
use App\Jobs\CalculateIntersectionsJob;
use App\Jobs\CheckNearbyHutsJob;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OneTimeImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:one-time-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all necessary data for OSM2CAI including legacy data, entities (hiking routes, mountain groups, POIs) and their relationships.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting '.env('APP_NAME').' one-time import...');

        $this->importEntities();
        $this->importLegacyData();
        $this->computeRelationships();

        $this->info('Import completed successfully!');
    }

    private function importLegacyData()
    {
        $this->info('=== Importing Legacy Data ===');

        $commands = [
            ['command' => 'osm2cai:sync-users', 'description' => 'Syncing users from legacy OSM2CAI'],
            ['command' => 'osm2cai:associate-users-to-ec-pois', 'description' => 'Associating users to EC POIs'],
            ['command' => 'osm2cai:assign-region-code', 'description' => 'Assigning region codes'],
            ['command' => 'osm2cai:import-municipality-data', 'description' => 'Importing municipality data'],
        ];

        foreach ($commands as $cmd) {
            $this->info('Running: '.$cmd['description']);
            Artisan::call($cmd['command']);
            $this->info('✓ '.$cmd['description'].' completed');
        }
    }

    private function importEntities()
    {
        $this->info('=== Importing Entities ===');

        $entities = [
            'areas',
            'sectors',
            'sections',
            'mountain_groups',
            'cai_huts',
            'natural_springs',
            'itineraries',
        ];

        foreach ($entities as $entity) {
            $this->info("Dispatching jobs for {$entity}...");
            Artisan::call('osm2cai:sync', ['model' => $entity]);
            $this->info("✓ {$entity} jobs dispatched successfully");
        }

        // Additional computations
        $this->info('Computing additional data...');

        Artisan::call('osm2cai:set-expected-values');
        Artisan::call('osm2cai:associate-clubs-to-hiking-routes');
        Artisan::call('osm2cai:compute-hiking-routes-tdh');

        $this->info('✓ Additional computations completed');
    }

    private function computeRelationships()
    {
        $this->info('=== Computing Geometric Relationships ===');

        // Process hiking routes
        $hikingRoutes = HikingRoute::all();
        $totalRoutes = $hikingRoutes->count();

        $this->info("Processing {$totalRoutes} hiking routes...");
        $bar = $this->output->createProgressBar($totalRoutes);

        foreach ($hikingRoutes as $hikingRoute) {
            $hikingRoute->dispatchGeometricComputationsJobs();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ Hiking routes relationships computed');

        // Process mountain groups
        $mountainGroups = MountainGroups::all();
        $totalGroups = $mountainGroups->count();

        $this->info("Processing {$totalGroups} mountain groups...");
        $bar = $this->output->createProgressBar($totalGroups);

        foreach ($mountainGroups as $mountainGroup) {
            $mountainGroup->dispatchGeometricComputationsJobs();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ Mountain groups relationships computed');

        // Process EC POIs
        $ecPois = EcPoi::all();
        $totalPois = $ecPois->count();
        $buffer = config('osm2cai.hiking_route_buffer');

        $this->info("Processing {$totalPois} EC POIs...");
        $bar = $this->output->createProgressBar($totalPois);

        foreach ($ecPois as $ecPoi) {
            CheckNearbyHutsJob::dispatch($ecPoi, $buffer);
            CalculateIntersectionsJob::dispatch($ecPoi, Club::class)->onQueue('geometric-computations');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ EC POIs relationships computed');
    }
}
