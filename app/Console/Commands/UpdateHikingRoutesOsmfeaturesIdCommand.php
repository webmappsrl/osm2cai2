<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateHikingRoutesOsmfeaturesIdCommand extends Command
{
    protected $signature = 'osm2cai:update-osmfeatures-id';

    protected $description = 'Updates the osmfeatures_id column of HikingRoute based on properties.osm_id from osmfeatures_data';

    public function handle()
    {
        $logger = Log::channel('hiking-routes-update');
        $hikingRoutes = HikingRoute::all();
        $count = $hikingRoutes->count();

        $this->info("Processing {$count} hiking routes...");
        $logger->info("Starting osmfeatures_id update for {$count} routes");

        $bar = $this->output->createProgressBar($count);
        $updated = 0;
        $errors = 0;

        foreach ($hikingRoutes as $route) {
            try {
                $osmData = $route->osmfeatures_data;

                if (isset($osmData['properties']['osm_id'])) {
                    $osmId = $osmData['properties']['osm_id'];
                    $newOsmfeaturesId = 'R'.$osmId;

                    $route->osmfeatures_id = $newOsmfeaturesId;
                    $route->save();

                    $updated++;
                } else {
                    $logger->warning("OSM ID not found for route {$route->id}");
                    $errors++;
                }
            } catch (\Exception $e) {
                $logger->error("Error updating route {$route->id}: ".$e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
        $this->info('Update completed:');
        $this->info("- Updated routes: {$updated}");
        $this->info("- Errors: {$errors}");

        $logger->info("Update completed - Updated: {$updated}, Errors: {$errors}");
    }
}
