<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;

class ConvertOsmfeaturesDataToArray extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:convert-osmfeatures-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert HikingRoute records with osmfeatures_data as JSON string to array format';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Cercando record HikingRoute con osmfeatures_data come stringa JSON...');

        $hikingRoutes = HikingRoute::all('id', 'osmfeatures_data');
        $count = 0;
        $stringJsonRecords = [];


        $this->info('Totale record trovati: '.$hikingRoutes->count());
        $this->newLine();

        $bar = $this->output->createProgressBar($hikingRoutes->count());
        $bar->start();

        foreach ($hikingRoutes as $route) {

            if (is_string($route->osmfeatures_data) && ! empty($route->osmfeatures_data)) {
                try {
                    $decodedData = json_decode($route->osmfeatures_data, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $route = HikingRoute::find($route->id);
                        $route->osmfeatures_data = $decodedData;
                        $route->save();

                        $stringJsonRecords[] = [
                            'id' => $route->id,
                            'osm_id' => $decodedData['properties']['osm_id'] ?? 'N/A',
                        ];

                        $count++;
                    } else {
                        $this->error("Errore nella conversione JSON per il record ID: {$route->id}. Errore: ".json_last_error_msg());
                    }
                } catch (\Exception $e) {
                    $this->error("Eccezione durante l'elaborazione del record ID: {$route->id}. Errore: {$e->getMessage()}");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        if ($count > 0) {
            $this->info("Conversione completata! {$count} record sono stati convertiti da stringa JSON ad array.");

            if ($this->confirm('Vuoi vedere l\'elenco dei record convertiti?')) {
                $this->table(
                    ['ID', 'OSM ID'],
                    $stringJsonRecords
                );
            }
        } else {
            $this->info('Nessun record da convertire trovato. Tutti i record hanno già osmfeatures_data in formato array.');
        }

        return 0;
    }
}
