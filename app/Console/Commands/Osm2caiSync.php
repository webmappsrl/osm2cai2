<?php

namespace App\Console\Commands;

use App\Jobs\ImportElementFromOsm2caiJob;
use Illuminate\Console\Command;
use Illuminate\Support\DomParser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    protected $description = 'Perform a data import from legacy OSM2CAI API to the current database for the specified model (current models: mountain_groups, natural_springs, areas, sectors, sections, itineraries, cai_huts)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->argument('model');
        $modelClass = $this->parseModelClass($model);

        if ($modelClass === null) {
            $this->error('Model class not found');
            Log::error('Model'.$modelClass.' class not found');

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
                $this->error('Failed to retrieve data from API: '.$listApi);
                Log::error('Failed to retrieve data from API: '.$listApi.' '.$response->body());

                return;
            }
        }

        $data = $response->json();

        $this->info('Dispatching '.count($data).' jobs for '.$model.' model');
        $progressBar = $this->output->createProgressBar(count($data));
        $progressBar->start();

        $batchSize = 1000;
        $batch = [];

        foreach ($data as $id => $udpated_at) {
            $modelInstance = new $modelClass();
            if ($modelInstance->where('id', $id)->exists() && ! $modelInstance instanceof \App\Models\HikingRoute) {
                $progressBar->advance();
                continue;
            }
            $singleFeatureApi = "https://osm2cai.cai.it/api/v2/export/$model/$id";
            $batch[] = new ImportElementFromOsm2caiJob($modelClass, $singleFeatureApi);

            if (count($batch) >= $batchSize) {
                Bus::batch($batch)->dispatch();
                $batch = [];
                usleep(500000);
            }
            $progressBar->advance();
        }

        if (! empty($batch)) {
            Bus::batch($batch)->dispatch();
        }

        $progressBar->finish();

        $this->info(''); // Add an empty line
        $this->info('Jobs dispatched');
        Log::info('Jobs dispatched');
    }

    private function parseModelClass($model)
    {
        $model = str_replace('_', '', ucwords($model, '_'));
        $modelClass = 'App\\Models\\'.$model;

        if (! class_exists($modelClass)) {
            //remove final 's' from model name
            $modelName = substr($model, 0, -1);
            $modelClass = 'App\\Models\\'.$modelName;
            if (! class_exists($modelClass)) {
                //rename section model to club
                if ($model === 'Sections') {
                    $modelClass = 'App\\Models\\Club';
                } elseif ($model === 'Itineraries') {
                    $modelClass = 'App\\Models\\Itinerary';
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
            case 'CaiHut':
                return 'huts';
                break;
            case 'cai_huts':
                return 'huts';
                break;
            case 'MountainGroups':
                return 'mountain_groups';
            case 'NaturalSpring':
                return 'natural_springs';
            case 'EcPoi':
                return 'ec_pois';
            case 'UgcPoi':
                return 'ugc_pois';
            case 'UgcTrack':
                return 'ugc_tracks';
            case 'UgcMedia':
                return 'ugc_media';
            case 'Area':
                return 'areas';
            case 'Sector':
                return 'sectors';
            case 'Section':
                return 'sections';
            case 'Itinerary':
                return 'itineraries';
            default:
                return $model;
        }
    }
}
