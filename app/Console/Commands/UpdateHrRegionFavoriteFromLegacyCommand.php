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
        $legacyConnection = DB::connection('legacyosm2cai');

        //get all the hiking route with region_favorite = true
        $legacyHr = $legacyConnection->table('hiking_routes')->where('region_favorite', true)->get();

        foreach ($legacyHr as $lhr) {

            $osmfeaturesId = 'R' . $lhr->relation_id;
            $newHr = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();
            if ($newHr) {
                $newHr->updateQuietly(['region_favorite' => true]);
                $newHr->saveQuietly();
            }
        }

        $this->info('Region favorite Hiking Routes updated successfully');
        Log::info('Region favorite Hiking Routes updated successfully');
    }
}
