<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\DomParser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Jobs\ImportElementFromOsm2cai;

class Osm2caiSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a data import from OSM2CAI API to the current database for the specified model (e.g. mountain_groups, natural_springs, etc)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->argument('model');
        $modelClass = $this->parseModelClass($model);

        if ($modelClass === null) {
            $this->error('Model class not found');
            Log::error('Model' . $modelClass . ' class not found');

            return;
        }

        $listApi = "https://osm2cai.cai.it/api/v2/export/$model/list";

        //perform the request to the API
        $response = Http::get($listApi);

        if ($response->failed() || $response->json() === null) {
            //renaming the model to match the API endpoint
            $model = $this->mapModelToendPoint($model);
            $listApi = "https://osm2cai.cai.it/api/v2/export/$model/list";
            $response = Http::get($listApi);
            if ($response->failed() || $response->json() === null) {
                $this->error('Failed to retrieve data from API: ' . $listApi);
                Log::error('Failed to retrieve data from API: ' . $listApi . ' ' . $response->body());

                return;
            }
        }

        $data = $response->json();

        $this->info('Dispatching ' . count($data) . ' jobs for ' . $model . ' model');
        $progressBar = $this->output->createProgressBar(count($data));
        $progressBar->start();

        $batchSize = 1000;
        $batch = [];

        foreach ($data as $id => $udpated_at) {
            $modelInstance = new $modelClass();
            //if the model already exists in the database skip the import
            if ($modelInstance->where('id', $id)->exists() && !$modelInstance instanceof \App\Models\HikingRoute) {
                $progressBar->advance();
                continue;
            }
            $singleFeatureApi = "https://osm2cai.cai.it/api/v2/export/$model/$id";
            $batch[] = new ImportElementFromOsm2cai($modelClass, $singleFeatureApi);

            // When the batch size is reached, dispatch all jobs in the batch
            if (count($batch) >= $batchSize) {
                Bus::batch($batch)->dispatch();
                $batch = []; // Reset the batch
                usleep(500000); // Sleep for 500 milliseconds to reduce load
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->info(''); // Add an empty line
        $this->info('Jobs dispatched');
        Log::info('Jobs dispatched');
    }

    private function parseModelClass($model)
    {
        $model = str_replace('_', '', ucwords($model, '_'));
        $modelClass = 'App\\Models\\' . $model;

        if (! class_exists($modelClass)) {
            //remove final 's' from model name
            $modelName = substr($model, 0, -1);
            $modelClass = 'App\\Models\\' . $modelName;
            if (! class_exists($modelClass)) {
                //rename section model to club
                if ($modelName === 'Section') {
                    $modelClass = 'App\\Models\\Club';
                } else {
                    return null;
                }
            }
        }

        return $modelClass;
    }

    private function mapModelToendPoint($model)
    {
        switch ($model) {
            case 'cai_huts':
                return 'huts';
                break;
            case 'HikingRoute':
                return 'hiking-routes';
                break;
        }
    }
}
