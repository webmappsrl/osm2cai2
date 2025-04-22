<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGeometryRawDataFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:import-geometry-raw-data-from-legacy {--chunk=100 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import geometry raw data from legacy database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting import of geometry raw data from legacy database...');

        $chunkSize = (int) $this->option('chunk');
        $updated = 0;
        $notFound = 0;
        $totalRoutes = HikingRoute::whereNull('geometry_raw_data')->count();

        if ($totalRoutes === 0) {
            $this->info('No hiking routes with null geometry_raw_data found.');
            return 0;
        }

        $this->info("Found {$totalRoutes} hiking routes with null geometry_raw_data");
        $this->info("Processing in chunks of {$chunkSize}");

        // Create a progress bar
        $progressBar = $this->output->createProgressBar($totalRoutes);
        $progressBar->start();

        // Process in chunks to reduce memory usage
        HikingRoute::whereNull('geometry_raw_data')
            ->select(['id', 'osmfeatures_id'])
            ->chunkById($chunkSize, function ($hikingRoutes) use (&$updated, &$notFound, $progressBar) {
                // Extract all OSM IDs for this chunk
                $osmIds = $hikingRoutes->map(function ($route) {
                    return substr($route->osmfeatures_id, 1);
                })->toArray();

                // Fetch all matching records from legacy DB in a single query
                $legacyRoutes = DB::connection('legacyosm2cai')
                    ->table('hiking_routes')
                    ->whereIn('relation_id', $osmIds)
                    ->select(['relation_id', 'geometry_raw_data'])
                    ->get()
                    ->keyBy('relation_id');

                // Create a batch update array
                $updates = [];
                foreach ($hikingRoutes as $hikingRoute) {
                    $osmId = substr($hikingRoute->osmfeatures_id, 1);

                    if ($legacyRoutes->has($osmId)) {
                        $updates[] = [
                            'id' => $hikingRoute->id,
                            'geometry_raw_data' => $legacyRoutes[$osmId]->geometry_raw_data
                        ];
                        $updated++;
                    } else {
                        $notFound++;
                    }

                    $progressBar->advance();
                }

                // Perform batch update if we have updates
                if (!empty($updates)) {
                    DB::transaction(function () use ($updates) {
                        foreach ($updates as $update) {
                            HikingRoute::where('id', $update['id'])
                                ->update(['geometry_raw_data' => $update['geometry_raw_data']]);
                        }
                    });
                }
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Import completed. Updated: {$updated} routes. Not found in legacy: {$notFound} routes.");

        return 0;
    }
}
