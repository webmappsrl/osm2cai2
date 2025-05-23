<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateHrRegionFavoriteFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:update-hr-region-favorite-from-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command populate the region_favorite column retrieving data from legacy osm2cai DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[START] Updating region favorite hiking routes from legacy database...');
        $legacyConnection = DB::connection('legacyosm2cai');

        // get all the hiking route with region_favorite = true
        $legacyHr = $legacyConnection->table('hiking_routes')->where('region_favorite', true)->get();

        $this->info('Found '.count($legacyHr).' hiking routes to update');

        $progressBar = $this->output->createProgressBar(count($legacyHr));
        $progressBar->start();

        foreach ($legacyHr as $lhr) {
            $osmfeaturesId = 'R'.$lhr->relation_id;
            $newHr = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();
            if ($newHr) {
                $newHr->updateQuietly(['region_favorite' => true]);
                $newHr->saveQuietly();
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Region favorite Hiking Routes updated successfully');
        Log::info('Region favorite Hiking Routes updated successfully');
    }
}
