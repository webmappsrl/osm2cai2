<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CheckMissingEcPoisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:check-missing-ec-pois';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare POI osmfeatures_ids with the list in storage and find missing ones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting missing POI check...');

        // Read IDs from database
        $dbIds = DB::table('ec_pois')
            ->whereNotNull('osmfeatures_id')
            ->pluck('osmfeatures_id')
            ->toArray();

        // Read IDs from file
        $fileContent = Storage::disk('public')->get('ec_pois.txt');
        $fileIds = array_filter(explode("\n", $fileContent));

        // Find missing IDs (present in file but not in database)
        $missingIds = array_diff($fileIds, $dbIds);

        if (count($missingIds) > 0) {
            // Make sure directory exists
            Storage::disk('public')->makeDirectory('');

            // Save missing IDs to new file
            Storage::disk('public')->put(
                'missing_ec_pois.txt',
                implode("\n", array_values($missingIds))
            );

            $this->info('Completed! Missing IDs have been saved to storage/app/public/missing_ec_pois.txt');
            $this->info('Number of missing IDs found: '.count($missingIds));
        } else {
            $this->info('No missing IDs found.');
        }
    }
}
