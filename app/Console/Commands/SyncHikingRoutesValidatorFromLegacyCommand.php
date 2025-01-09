<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncHikingRoutesValidatorFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync-hiking-routes-validator-from-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize validator_id and validation_date from legacy hiking_routes records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $legacyConnection = DB::connection('legacyosm2cai');

        // Get all records with user_id not null
        $legacyHikingRoutes = $legacyConnection->table('hiking_routes')
            ->whereNotNull('user_id')
            ->get();

        $this->info('Found '.count($legacyHikingRoutes).' hiking routes to update');
        $progressBar = $this->output->createProgressBar(count($legacyHikingRoutes));

        foreach ($legacyHikingRoutes as $legacyHr) {
            $osmfeaturesId = 'R'.$legacyHr->relation_id;

            // Find the corresponding record in the current database
            $hikingRoute = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

            if ($hikingRoute) {
                $hikingRoute->updateQuietly([
                    'validator_id' => $legacyHr->user_id,
                    'validation_date' => $legacyHr->validation_date,
                ]);

                $logMessage = "Updated route {$osmfeaturesId} with validator_id {$legacyHr->user_id}";
                Log::info($logMessage);
            } else {
                $logMessage = "Route {$osmfeaturesId} not found in current database";
                Log::warning($logMessage);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Synchronization completed successfully');
        Log::info('Validator_id and validation_date synchronization completed');
    }
}
