<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class GetTaxonomyWheresFromOsmfeaturesJob implements ShouldQueue
{
    use Queueable;

    public $model;
    public $osmfeaturesApi;

    /**
     * Create a new job instance.
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->osmfeaturesApi = 'https://osmfeatures.maphub.it/api/v1/features/admin-areas/geojson';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->buildPayload();

        try {
            $response = Http::timeout(60)->withHeaders([
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->osmfeaturesApi, $payload);

            if ($response->successful()) {
                $this->addTaxonomyWheresToModel($response->json());
            } else {
                throw new \Exception("Failed to get taxonomy wheres for {$this->model->getTable()} {$this->model->id}. Status: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    private function buildPayload()
    {

        $geojson = DB::table($this->model->getTable())
            ->where('id', $this->model->id)
            ->value(DB::raw('ST_AsGeoJSON(geometry)'));

        if (!$geojson) {
            throw new \Exception("No geometry found for {$modelTable} {$this->model->id}. Skipping...");
        }

        $feature = [
            'type' => 'Feature',
            'geometry' => json_decode($geojson, true),
            'properties' => [
                'name' => $this->model->properties['name'] ?? "{$this->model->getTable()} {$this->model->id}",
            ],
        ];

        $payload = [
            'geojson' => $feature,
        ];

        return $payload;
    }

    private function addTaxonomyWheresToModel($response)
    {
        $taxonomyWheresString = '';
        foreach ($response['features'] as $feature) {
            $taxonomyWhere = $feature['properties']['name'];
            $taxonomyWheresString .= $taxonomyWhere . ', ';
        }

        //  Indirect modification of overloaded property ::$properties has no effect so i should extract the properties to a variable and then set the new value
        $properties = $this->model->properties;
        $properties['taxonomy_where'] = $taxonomyWheresString;
        $this->model->properties = $properties;
        $this->model->saveQuietly();
    }
}
