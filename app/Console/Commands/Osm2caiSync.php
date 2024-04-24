<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
    protected $signature = 'osm2cai:sync {model} {--skip-already-imported}';

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
        $skip = $this->option('skip-already-imported');
        $modelClass = "App\\Models\\" . str_replace('_', '', ucwords($model, '_'));

        if (!class_exists($modelClass)) {
            $this->error('Model class not found');
            Log::error('Model' . $modelClass . ' class not found');
            return;
        }

        $listApi = "https://osm2cai.cai.it/api/v2/export/$model/list";

        //perform the request to the API
        $response = Http::get($listApi);

        if ($response->failed()) {
            $this->error('Failed to retrieve data from OSM2CAI API');
            Log::error('Failed to retrieve data from OSM2CAI API' . $response->body());
        }

        $data = $response->json();

        $this->info('Dispatching ' . count($data) . ' jobs for ' . $model . ' model');
        $progressBar = $this->output->createProgressBar(count($data));
        $progressBar->start();

        foreach ($data as $id => $udpated_at) {
            $singleFeatureApi = "https://osm2cai.cai.it/api/v2/export/$model/$id";
            dispatch(new ImportElementFromOsm2cai($modelClass, $singleFeatureApi, $skip));
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->info(''); // Add an empty line
        $this->info('Jobs dispatched');
        Log::info('Jobs dispatched');
    }
}