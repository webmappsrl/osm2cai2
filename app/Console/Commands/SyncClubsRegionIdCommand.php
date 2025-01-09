<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncClubsRegionIdCommand extends Command
{
    protected $signature = 'osm2cai:sync-clubs-region-id';

    protected $description = 'Populates the region_id column in the clubs table by matching records with the legacy osm2cai database (sections table)';

    public function handle()
    {
        $legacyConnection = DB::connection('legacyosm2cai');
        $legacySections = $legacyConnection->table('sections')->whereNotNull('region_id')->get();

        $this->info('Found '.$legacySections->count().' sections in legacy database');
        $progressBar = $this->output->createProgressBar($legacySections->count());

        $updated = 0;
        $errors = [];

        foreach ($legacySections as $legacySection) {
            try {
                // Find current Club using osmfeatures_id
                $currentClub = Club::where('name', $legacySection->name)->first();

                if (! $currentClub) {
                    $errors[] = "Club not found with name: {$legacySection->name}";
                    continue;
                }

                // Get region code from legacy database
                $legacyRegion = $legacyConnection->table('regions')
                    ->where('id', $legacySection->region_id)
                    ->first();

                if (! $legacyRegion) {
                    $errors[] = "Legacy region not found with ID: {$legacySection->region_id}";
                    continue;
                }

                // Find current region using code
                $currentRegion = Region::where('code', $legacyRegion->code)->first();

                if (! $currentRegion) {
                    $errors[] = "Current region not found with code: {$legacyRegion->code}";
                    continue;
                }

                // Update current Club's region_id
                $currentClub->region_id = $currentRegion->id;
                $currentClub->saveQuietly();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Error processing section {$legacySection->id}: ".$e->getMessage();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully updated: $updated Clubs");

        if (count($errors) > 0) {
            $this->error('Errors encountered: '.count($errors));
            foreach ($errors as $error) {
                Log::error($error);
            }
        }
    }
}
