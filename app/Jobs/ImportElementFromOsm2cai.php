<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class ImportElementFromOsm2cai implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IsMonitored;

    protected $apiUrl;

    protected $modelClass;

    protected $skipAlreadyImported;

    /**
     * Create a new job instance.
     */
    public function __construct(string $modelClass, string $apiUrl)
    {
        $this->modelClass = $modelClass;
        $this->apiUrl = $apiUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->queueProgress(0);

        $response = Http::get($this->apiUrl);

        if ($response->failed()) {
            Log::error('Failed to retrieve data from OSM2CAI API' . $response->body());
            return;
        }

        $data = $response->json();
        $this->queueProgress(50);

        $model = new $this->modelClass();

        if ($this->skipAlreadyImported && $model->where('id', $data['id'])->exists()) {
            Log::info($model . ' with id: ' . $data['id'] . ' already imported, skipping');

            return;
        }

        if ($model instanceof \App\Models\MountainGroups) {
            $this->importMountainGroups($model, $data);
        }
        if ($model instanceof \App\Models\NaturalSpring) {
            $this->importNaturalSprings($model, $data);
        }

        $this->queueProgress(100);
    }

    private function importMountainGroups($model, $data)
    {
        $columnsToImport = ['id', 'name', 'description', 'geometry', 'aggregated_data', 'intersectings'];

        $data['intersectings'] = [
            'hiking_routes' => json_decode($data['hiking_routes_intersecting'], true),
            'sections' => json_decode($data['sections_intersecting'], true),
            'huts' => json_decode($data['huts_intersecting']),
            'ec_pois' => json_decode($data['ec_pois_intersecting'], true)
        ];

        $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($data['geometry']) . "'), 4326)");

        $intersect = array_intersect_key($data, array_flip($columnsToImport));
        $intersect['aggregated_data'] = json_encode($intersect['aggregated_data']);
        $intersect['intersectings'] = json_encode($intersect['intersectings']);

        $model->updateOrCreate(['id' => $data['id']], $intersect);
    }

    private function importNaturalSprings($model, $data)
    {
        $columnsToImport = ['id', 'code', 'loc_ref', 'source', 'source_ref', 'source_code', 'name', 'region', 'province', 'municipality', 'operator', 'type', 'volume', 'time', 'mass_flow_rate', 'temperature', 'conductivity', 'survey_date', 'lat', 'lon', 'elevation', 'note', 'geometry'];

        $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($data['geometry']) . "'), 4326)");

        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        $model->updateOrCreate(['id' => $data['id']], $intersect);
    }
}
