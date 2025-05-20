<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create 29 regions with names
        $regionNames = 'Auvergne-Rhône-Alpes,Toscana,Campania,Emilia-Romagna,Friuli-Venezia Giulia,Marche,Puglia,Veneto,Calabria,Lazio,Liguria,Molise,Piemonte,Umbria,Ticino,Lombardia,Sicilia,Valle d\'Aosta / Vallée d\'Aoste,Valais/Wallis,Abruzzo,Basilicata,Trentino-Alto Adige/Südtirol,Graubünden/Grischun/Grigioni,Salzburg,Corse,Kärnten,Provence-Alpes-Côte d\'Azur,Tirol,Sardegna';
        $regions = explode(',', $regionNames);
        foreach ($regions as $regionName) {
            Region::create(['name' => $regionName]);
        }
    }
}
