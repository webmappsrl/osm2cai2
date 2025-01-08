<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Jobs\OsmfeaturesSyncJob;

class SetHrOsm2caiStatus4Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:set-hr-osm2cai-status-4';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command connects to legacy osm2cai db and set osm2cai_status to 4 for hiking routes with status 4 matching the relation_id with the osmfeatures_id. If the hiking route is not found, it dispatches a job to fetch the hiking route from the osmfeatures api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $legacyConnection = DB::connection('legacyosm2cai');

        //get all the legacy hiking routes with osm2cai_status = 4 (only relation_id and osm2cai_status)
        $legacyHikingRoutes = $legacyConnection->table('hiking_routes')->select('relation_id')->where('osm2cai_status', 4)->get();
        $this->info('Found ' . count($legacyHikingRoutes) . ' hiking routes with osm2cai_status 4');

        $progressBar = $this->output->createProgressBar(count($legacyHikingRoutes));
        $jobsDispatched = 0;
        $notFoundHikingRoutes = [];

        //for each hiking route, check if the relation_id exists in the osmfeatures table
        foreach ($legacyHikingRoutes as $legacyHikingRoute) {
            $osmfeaturesId = 'R' . $legacyHikingRoute->relation_id;
            $hikingRoute = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();
            if ($hikingRoute) {
                $hikingRoute->osm2cai_status = 4;
                $hikingRoute->saveQuietly();
            } else {
                dispatch(new OsmfeaturesSyncJob($osmfeaturesId, HikingRoute::class));
                $jobsDispatched++;
                $notFoundHikingRoutes[] = 'https://www.openstreetmap.org/relation/' . $legacyHikingRoute->relation_id;
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        // Write not found hiking routes to file
        if (! empty($notFoundHikingRoutes)) {
            file_put_contents(
                storage_path('not_found_hiking_routes_status_4.txt'),
                implode("\n", $notFoundHikingRoutes) . "\n"
            );
        }

        $this->info("\nSync jobs dispatched for missing hiking routes: $jobsDispatched");
        if (! empty($notFoundHikingRoutes)) {
            $this->info('List of not found hiking routes written to: ' . storage_path('not_found_hiking_routes_status_4.txt'));
        }
        Log::info('SetHrOsm2caiStatus4Command finished');
    }
}
