<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncHikingRoutesDescriptionCaiItCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync-hiking-routes-description-cai-it';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync hiking routes description_cai_it from legacy osm2cai';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[START] Sync hiking routes description_cai_it from legacy osm2cai...');
        $legacyConnection = DB::connection('legacyosm2cai');

        // Get all hiking routes from legacy osm2cai that have description_cai_it
        $legacyHikingRoutes = $legacyConnection->table('hiking_routes')
            ->whereNotNull('description_cai_it')
            ->where('description_cai_it', '!=', '')
            ->select('relation_id', 'description_cai_it')
            ->get();

        $this->info('Found ' . count($legacyHikingRoutes) . ' hiking routes with description_cai_it to import');

        $progressBar = $this->output->createProgressBar(count($legacyHikingRoutes));
        $progressBar->start();

        $updated = 0;
        $notFound = [];

        // Use a transaction to improve performance
        DB::beginTransaction();

        try {
            foreach ($legacyHikingRoutes as $legacyHr) {
                $osmfeaturesId = 'R' . $legacyHr->relation_id;

                // Find the corresponding hiking route in the current database
                $currentHr = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

                if ($currentHr) {
                    try {
                        $currentHr->update([
                            'description_cai_it' => $legacyHr->description_cai_it
                        ]);
                        $updated++;
                    } catch (\Exception $e) {
                        Log::error('Error updating description_cai_it for hiking route ' . $osmfeaturesId . ': ' . $e->getMessage());
                        $notFound[] = $osmfeaturesId;
                    }
                } else {
                    $notFound[] = $osmfeaturesId;
                }

                $progressBar->advance();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during description_cai_it synchronization: ' . $e->getMessage());
            $this->error('An error occurred during synchronization: ' . $e->getMessage());
        }

        $progressBar->finish();
        $this->newLine();

        // Write not found hiking routes to a file
        if (!empty($notFound)) {
            $notFoundFile = storage_path('hiking_routes_description_cai_it_not_found.txt');
            file_put_contents(
                $notFoundFile,
                implode("\n", $notFound) . "\n"
            );
            $this->info('List of not found hiking routes written to: ' . $notFoundFile);
        }

        $this->info('Update successfully completed for ' . $updated . ' hiking routes');
        $this->info('Hiking routes not found: ' . count($notFound));

        Log::info('SyncHikingRoutesDescriptionCaiItCommand completed: ' . $updated . ' updated, ' . count($notFound) . ' not found');
    }
}
