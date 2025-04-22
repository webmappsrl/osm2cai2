<?php

namespace App\Console\Commands;

use App\Jobs\SyncClubHikingRouteRelationJob;
use App\Models\Club;
use App\Models\HikingRoute;
use Illuminate\Console\Command;

class AssociateClubsToHikingRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:associate-clubs-to-hiking-routes 
                            {--model= : The model to process (Club or HikingRoute)}
                            {--id= : The ID of the specific model to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches jobs to synchronize relationships between hiking routes and clubs based on CAI code and source_ref.';

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
        $this->info('[START] Dispatching jobs to sync relationships between hiking routes and clubs');

        $modelType = $this->option('model');
        $modelId = $this->option('id');

        // Validate model type if ID is provided
        if ($modelId && !$modelType) {
            $this->error('The --model option (Club or HikingRoute) is required when specifying an --id.');
            return 1;
        }
        if ($modelType && !in_array($modelType, ['Club', 'HikingRoute'])) {
            $this->error('Invalid --model specified. Use Club or HikingRoute.');
            return 1;
        }

        // Dispatch the job with appropriate parameters
        if ($modelType && $modelId) {
            // Dispatch for a specific model instance
            $this->info("Dispatching job for {$modelType} ID: {$modelId}");
            SyncClubHikingRouteRelationJob::dispatch($modelType, (int)$modelId); // Pass type and ID
        } elseif ($modelType === 'Club') {
            $this->info("Dispatching jobs for all Clubs...");
            Club::chunk(100, function ($clubs) {
                foreach ($clubs as $club) {
                    SyncClubHikingRouteRelationJob::dispatch('Club', $club->id);
                }
            });
        } elseif ($modelType === 'HikingRoute') {
            $this->info("Dispatching jobs for all Hiking Routes...");
            HikingRoute::chunk(100, function ($routes) {
                foreach ($routes as $route) {
                    SyncClubHikingRouteRelationJob::dispatch('HikingRoute', $route->id);
                }
            });
        } else {
            // Default behavior: dispatch one job to handle all clubs
            $this->info('Dispatching job to process all clubs.');
            SyncClubHikingRouteRelationJob::dispatch(); // No parameters means 'process all clubs' in the job
        }

        $this->info('[END] Job dispatching complete.');

        return 0;
    }
}
