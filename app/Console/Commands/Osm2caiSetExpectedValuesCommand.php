<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Osm2caiSetExpectedValuesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:set-expected-values';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the expected number of hiking routes for the regions, provinces, areas and sectors';

    private $legacyOsm2cai;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->legacyOsm2cai = DB::connection('legacyosm2cai');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Setting expected values for regions...');
        $regions = [
            ['name' => 'Abruzzo', 'expected' => 449],
            ['name' => 'Basilicata', 'expected' => 200],
            ['name' => 'Calabria', 'expected' => 357],
            ['name' => 'Campania', 'expected' => 559],
            ['name' => 'Emilia-Romagna', 'expected' => 1253],
            ['name' => 'Friuli-Venezia Giulia', 'expected' => 645],
            ['name' => 'Lazio', 'expected' => 1033],
            ['name' => 'Liguria', 'expected' => 806],
            ['name' => 'Lombardia', 'expected' => 3784],
            ['name' => 'Marche', 'expected' => 602],
            ['name' => 'Molise', 'expected' => 35],
            ['name' => 'Piemonte', 'expected' => 4642],
            ['name' => 'Puglia', 'expected' => 64],
            ['name' => 'Sardigna/Sardegna', 'expected' => 445],
            ['name' => 'Sicilia', 'expected' => 444],
            ['name' => 'Toscana', 'expected' => 2684],
            ['name' => 'Trentino-Alto Adige/Südtirol', 'expected' => 4947],
            ['name' => 'Umbria', 'expected' => 444],
            ['name' => 'Valle d\'Aosta / Vallée d\'Aoste', 'expected' => 1070],
            ['name' => 'Veneto', 'expected' => 984],
        ];

        $bar = $this->output->createProgressBar(count($regions));
        $bar->start();

        foreach ($regions as $region) {
            Region::where('name', $region['name'])->first()->updateQuietly(['num_expected' => $region['expected']]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->setProvinceExpectedValues();
        $this->setAreaExpectedValues();
        $this->setSectorExpectedValues();
    }

    private function setProvinceExpectedValues()
    {
        $this->info('Setting expected values for provinces...');
        $legacyProvinces = $this->legacyOsm2cai->table('provinces')->get();

        $bar = $this->output->createProgressBar(count($legacyProvinces));
        $bar->start();

        foreach ($legacyProvinces as $legacyProvince) {
            $actualProvince = Province::where('osmfeatures_data->properties->osm_tags->short_name', $legacyProvince->code)
                ->orWhere('osmfeatures_data->properties->osm_tags->ref', $legacyProvince->code)
                ->first();
            if ($actualProvince) {
                $actualProvince->num_expected = $legacyProvince->num_expected;
                $actualProvince->saveQuietly();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function setAreaExpectedValues()
    {
        $this->info('Setting expected values for areas...');
        $legacyAreas = $this->legacyOsm2cai->table('areas')->get();

        $bar = $this->output->createProgressBar(count($legacyAreas));
        $bar->start();

        foreach ($legacyAreas as $legacyArea) {
            $actualArea = Area::where('full_code', $legacyArea->full_code)
                ->first();
            if ($actualArea) {
                $actualArea->num_expected = $legacyArea->num_expected;
                $actualArea->saveQuietly();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function setSectorExpectedValues()
    {
        $this->info('Setting expected values for sectors...');
        $legacySectors = $this->legacyOsm2cai->table('sectors')->get();

        $bar = $this->output->createProgressBar(count($legacySectors));
        $bar->start();

        foreach ($legacySectors as $legacySector) {
            $actualSector = Sector::where('full_code', $legacySector->full_code)
                ->first();
            if ($actualSector) {
                $actualSector->num_expected = $legacySector->num_expected;
                $actualSector->saveQuietly();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
