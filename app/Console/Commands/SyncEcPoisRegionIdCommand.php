<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEcPoisRegionIdCommand extends Command
{
    protected $signature = 'osm2cai:sync-ec-pois-region-id';

    protected $description = 'Populates the region_id column in the ec_pois table by matching records with the legacy osm2cai database';

    public function handle()
    {
        $legacyConnection = DB::connection('legacyosm2cai');
        $legacyPois = $legacyConnection->table('ec_pois')->whereNotNull('region_id')->get();

        $this->info('Found ' . $legacyPois->count() . ' POIs in legacy database');
        $progressBar = $this->output->createProgressBar($legacyPois->count());

        $updated = 0;
        $errors = [];

        foreach ($legacyPois as $legacyPoi) {
            try {
                // Find current POI using osmfeatures_id
                $currentPoi = EcPoi::where('osmfeatures_id', $legacyPoi->osmfeatures_id)->first();

                if (!$currentPoi) {
                    $errors[] = "POI not found with osmfeatures_id: {$legacyPoi->osmfeatures_id}";
                    continue;
                }

                // Get region code from legacy database
                $legacyRegion = $legacyConnection->table('regions')
                    ->where('id', $legacyPoi->region_id)
                    ->first();

                if (!$legacyRegion) {
                    $errors[] = "Legacy region not found with ID: {$legacyPoi->region_id}";
                    continue;
                }

                // Find current region using code
                $currentRegion = Region::where('code', $legacyRegion->code)->first();

                if (!$currentRegion) {
                    $errors[] = "Current region not found with code: {$legacyRegion->code}";
                    continue;
                }

                // Update current POI's region_id
                $currentPoi->region_id = $currentRegion->id;
                $currentPoi->saveQuietly();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Error processing POI {$legacyPoi->id}: " . $e->getMessage();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully updated: $updated POIs");

        if (count($errors) > 0) {
            $this->error("Errors encountered: " . count($errors));
            foreach ($errors as $error) {
                Log::error($error);
            }
        }
    }
}
