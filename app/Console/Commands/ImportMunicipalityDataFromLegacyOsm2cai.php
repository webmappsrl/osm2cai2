<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMunicipalityDataFromLegacyOsm2cai extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:import-municipality-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to the legacy osm2cai database and imports the municipality data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $columnsToUpdate = ['gid', 'cod_rip', 'cod_reg', 'cod_prov', 'cod_cm', 'cod_uts', 'pro_com', 'pro_com_t', 'comune_a', 'cc_uts', 'shape_leng'];
        //connect to the legacy osm2cai database
        $connection = DB::connection('legacyosm2cai');

        //get the municipality data
        $legacyMunicipalities = $connection->table('municipality_boundaries')->get();

        $updatedCount = 0;
        $notUpdated = [];

        //insert the data into the municipalities table
        foreach ($legacyMunicipalities as $legacyMunicipality) {
            //search the corresponding municipality in the new database
            $municipality = Municipality::where(function ($query) use ($legacyMunicipality) {
                $query->whereRaw('LOWER(name) = ?', [strtolower($legacyMunicipality->comune)])
                    ->orWhereRaw('LOWER(name) = ?', [strtolower(str_replace(' ', '-', $legacyMunicipality->comune))]);
            })->first();

            if ($municipality) {
                foreach ($columnsToUpdate as $column) {
                    $municipality->{$column} = $legacyMunicipality->{$column};
                }
                $municipality->save();
                $updatedCount++;
            } else {
                $notUpdated[] = $legacyMunicipality->comune;
            }
        }

        $this->info('Updated municipalities: '.$updatedCount);
        $this->info('Not updated municipalities: '.count($notUpdated));

        if (count($notUpdated) > 0) {
            $this->info('List of not updated municipalities:');
            foreach ($notUpdated as $comune) {
                $this->error('- '.$comune);
            }
        }
    }
}
