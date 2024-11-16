<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AssignRegionCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:assign-region-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign code to regions according to CAI convention';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /*
      Regions code according to CAI convention:

                'A' => 'Friuli Venezia Giulia',
                'B' => 'Veneto', 
                'C' => 'Trentino Alto Adige',
                'D' => 'Lombardia',
                'E' => 'Piemonte',
                'F' => "Val d'Aosta",
                'G' => 'Liguria',
                'H' => 'Emilia Romagna',
                'L' => 'Toscana',
                'M' => 'Marche',
                'N' => 'Umbria', 
                'O' => 'Lazio',
                'P' => 'Abruzzo',
                'Q' => 'Molise',
                'S' => 'Campania',
                'R' => 'Puglia',
                'T' => 'Basilicata',
                'U' => 'Calabria',
                'V' => 'Sicilia',
                'Z' => 'Sardegna'
      */

        $regionsCode = [
            'A' => 'Friuli-Venezia Giulia',
            'B' => 'Veneto',
            'C' => 'Trentino-Alto Adige',
            'D' => 'Lombardia',
            'E' => 'Piemonte',
            'F' => "Valle d'Aosta",
            'G' => 'Liguria',
            'H' => 'Emilia-Romagna',
            'L' => 'Toscana',
            'M' => 'Marche',
            'N' => 'Umbria',
            'O' => 'Lazio',
            'P' => 'Abruzzo',
            'Q' => 'Molise',
            'S' => 'Campania',
            'R' => 'Puglia',
            'T' => 'Basilicata',
            'U' => 'Calabria',
            'V' => 'Sicilia',
            'Z' => 'Sardegna'
        ];

        foreach ($regionsCode as $code => $name) {
            $region = Region::where('name', 'LIKE', '%' . $name . '%')->first();
            if ($region) {
                $region->code = $code;
                $region->save();
            }
        }
    }
}
