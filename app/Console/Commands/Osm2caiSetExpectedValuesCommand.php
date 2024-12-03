<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
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
    protected $description = 'Set the expected number of hiking routes for the regions';

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
        Region::where('name', 'Abruzzo')->first()->update(['num_expected' => 449]);
        Region::where('name', 'Basilicata')->first()->update(['num_expected' => 26]);
        Region::where('name', 'Calabria')->first()->update(['num_expected' => 234]);
        Region::where('name', 'Campania')->first()->update(['num_expected' => 559]);
        Region::where('name', 'Emilia-Romagna')->first()->update(['num_expected' => 1253]);
        Region::where('name', 'Friuli-Venezia Giulia')->first()->update(['num_expected' => 645]);
        Region::where('name', 'Lazio')->first()->update(['num_expected' => 1033]);
        Region::where('name', 'Liguria')->first()->update(['num_expected' => 806]);
        Region::where('name', 'Lombardia')->first()->update(['num_expected' => 3784]);
        Region::where('name', 'Marche')->first()->update(['num_expected' => 602]);
        Region::where('name', 'Molise')->first()->update(['num_expected' => 27]);
        Region::where('name', 'Piemonte')->first()->update(['num_expected' => 4635]);
        Region::where('name', 'Puglia')->first()->update(['num_expected' => 60]);
        Region::where('name', 'Sardigna/Sardegna')->first()->update(['num_expected' => 292]);
        Region::where('name', 'Sicilia')->first()->update(['num_expected' => 330]);
        Region::where('name', 'Toscana')->first()->update(['num_expected' => 2610]);
        Region::where('name', 'Trentino-Alto Adige/Südtirol')->first()->update(['num_expected' => 5162]);
        Region::where('name', 'Umbria')->first()->update(['num_expected' => 444]);
        Region::where('name', 'Valle d\'Aosta / Vallée d\'Aoste')->first()->update(['num_expected' => 1118]);
        Region::where('name', 'Veneto')->first()->update(['num_expected' => 984]);

        $this->setProvinceExpectedValues();
        $this->setAreaExpectedValues();
        $this->setSectorExpectedValues();
    }

    private function setProvinceExpectedValues()
    {
        $legacyProvinces = $this->legacyOsm2cai->table('provinces')->get();

        foreach ($legacyProvinces as $legacyProvince) {
            $actualProvince = Province::whereIn('osmfeatures_data->properties->osm_tags->short_name', $legacyProvince->code)
                ->orWhereIn('osmfeatures_data->properties->osm_tags->ref', $legacyProvince->code)
                ->first();
            if ($actualProvince) {
                $actualProvince->num_expected = $legacyProvince->num_expected;
                $actualProvince->save();
            }
        }
    }

    private function setAreaExpectedValues()
    {
        $legacyAreas = $this->legacyOsm2cai->table('areas')->get();

        foreach ($legacyAreas as $legacyArea) {
            $actualArea = Area::where('full_code', $legacyArea->full_code)
                ->first();
            if ($actualArea) {
                $actualArea->num_expected = $legacyArea->num_expected;
                $actualArea->save();
            }
        }
    }

    private function setSectorExpectedValues()
    {
        $legacySectors = $this->legacyOsm2cai->table('sectors')->get();

        foreach ($legacySectors as $legacySector) {
            $actualSector = Sector::where('full_code', $legacySector->full_code)
                ->first();
            if ($actualSector) {
                $actualSector->num_expected = $legacySector->num_expected;
                $actualSector->save();
            }
        }
    }
}
