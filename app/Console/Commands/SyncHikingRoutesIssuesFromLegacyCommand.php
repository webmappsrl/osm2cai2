<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncHikingRoutesIssuesFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:import-hiking-routes-issues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import hiking routes issues data from legacy osm2cai database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[START] Importing hiking routes issues from legacy database...');
        $legacyConnection = DB::connection('legacyosm2cai');

        // Get all hiking routes from legacy database
        $legacyHikingRoutes = $legacyConnection->table('hiking_routes')
            ->whereNotNull('issues_user_id')
            ->get();

        $this->info('Found '.count($legacyHikingRoutes).' hiking routes with issues to import');

        $progressBar = $this->output->createProgressBar(count($legacyHikingRoutes));
        $progressBar->start();

        $updated = 0;
        $notFound = [];

        foreach ($legacyHikingRoutes as $legacyHr) {
            $osmfeaturesId = 'R'.$legacyHr->relation_id;

            // Find corresponding hiking route in current database
            $currentHr = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

            if ($currentHr) {
                try {
                    $currentHr->update([
                        'issues_status' => $legacyHr->issues_status,
                        'issues_description' => $legacyHr->issues_description,
                        'issues_last_update' => $legacyHr->issues_last_update,
                        'issues_user_id' => $legacyHr->issues_user_id,
                        'issues_chronology' => $legacyHr->issues_chronology ? json_decode($legacyHr->issues_chronology, true) : null,
                    ]);
                    $updated++;
                } catch (\Exception $e) {
                    Log::error('Error updating hiking route '.$osmfeaturesId.': '.$e->getMessage());
                    $notFound[] = $osmfeaturesId;
                }
            } else {
                $notFound[] = $osmfeaturesId;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Successfully updated: '.$updated.' hiking routes');
        $this->info('Hiking routes not found: '.count($notFound));
    }
}
