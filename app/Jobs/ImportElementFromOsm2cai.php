<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
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
    public function __construct(string $modelClass, string $apiUrl, bool $skipAlreadyImported = false)
    {
        $this->modelClass = $modelClass;
        $this->apiUrl = $apiUrl;
        $this->skipAlreadyImported = $skipAlreadyImported;
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

        $this->queueProgress(100);
    }

    private function importMountainGroups($model, $data)
    {
        $columnsToImport = ['id', 'name', 'description', 'geometry', 'aggregated_data', 'intersectings'];

        $data['intersectings'] = ['hiking_routes' => json_decode($data['hiking_routes_intersecting'], true), 'sections' => json_decode($data['sections_intersecting'], true), 'huts' => json_decode($data['huts_intersecting']), 'ec_pois' => json_decode($data['ec_pois_intersecting'], true)];
        // Decodifica il campo geometry dal formato GeoJSON e lo converte in un oggetto ST_Geometry
        $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($data['geometry']) . "'), 4326)");

        $flip = array_flip($columnsToImport);
        $intersect = array_intersect_key($data, $flip);
        $intersect['aggregated_data'] = json_encode($intersect['aggregated_data']);
        $intersect['intersectings'] = json_encode($intersect['intersectings']);
        $model->updateOrCreate(
            ['id' => $data['id']],
            $intersect
        );
    }
}
