<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRegionProvincesRelationsFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync-region-provinces-relations-from-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command syncs the relations between regions and provinces from the legacy database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sync of region-province relations...');

        $legacyDbConnection = DB::connection('legacyosm2cai');
        $legacyRegions = $legacyDbConnection->table('regions')->get();
        $legacyProvinces = $legacyDbConnection->table('provinces')->get();

        $this->info('Found '.$legacyRegions->count().' regions and '.$legacyProvinces->count().' provinces in legacy database');

        $progressBar = $this->output->createProgressBar(count($legacyProvinces));
        $progressBar->start();

        foreach ($legacyProvinces as $legacyProvince) {
            $legacyRegion = $legacyRegions->where('id', $legacyProvince->region_id)->first();
            $currentRegion = Region::where('osmfeatures_id', $legacyRegion->osmfeatures_id)->first();
            $provinceCode = $legacyProvince->code;

            $this->line('Processing province: '.$provinceCode);

            // search for the corresponding province in the province table
            $currentProvince = Province::where('osmfeatures_data->properties->osm_tags->short_name', $provinceCode)
                ->orWhere('osmfeatures_data->properties->osm_tags->ref', $provinceCode)
                ->first();

            if (! $currentProvince) {
                $this->warn('Province not found with code: '.$provinceCode);

                continue;
            }

            $currentProvince->region_id = $currentRegion->id;
            $currentProvince->saveQuietly();

            $this->line('Linked province '.$provinceCode.' to region '.$currentRegion->name);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Successfully synced '.count($legacyProvinces).' provinces');
    }
}
