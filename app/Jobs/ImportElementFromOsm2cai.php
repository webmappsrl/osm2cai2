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
            Log::error('Failed to retrieve data from OSM2CAI API'.$response->body());

            return;
        }

        $data = $response->json();
        $this->queueProgress(50);

        $model = new $this->modelClass();

        if ($model->where('id', $data['id'])->exists()) {
            Log::info($model.' with id: '.$data['id'].' already imported, skipping');

            return;
        }

        $this->performImport($model, $data);

        $this->queueProgress(100);
    }

    private function performImport($model, $data)
    {
        if ($model instanceof \App\Models\MountainGroups) {
            try {
                $this->importMountainGroups($model, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Mountain Group with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($model instanceof \App\Models\NaturalSpring) {
            try {
                $this->importNaturalSprings($model, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Natural Spring with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($model instanceof \App\Models\CaiHut) {
            try {
                $this->importCaiHuts($model, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Cai Hut with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($model instanceof \App\Models\Club) {
            try {
                $this->importClubs($model, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Club with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($model instanceof \App\Models\Sector) {
            try {
                $this->importSectors($model, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Sector with id: '.$data['id'].' '.$e->getMessage());
            }
        }
    }

    private function importMountainGroups($model, $data)
    {
        $columnsToImport = ['id', 'name', 'description', 'geometry', 'aggregated_data', 'intersectings'];

        $data['intersectings'] = [
            'hiking_routes' => json_decode($data['hiking_routes_intersecting'], true),
            'sections' => json_decode($data['sections_intersecting'], true),
            'huts' => json_decode($data['huts_intersecting']),
            'ec_pois' => json_decode($data['ec_pois_intersecting'], true),
        ];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));
        $intersect['aggregated_data'] = json_encode($intersect['aggregated_data']);
        $intersect['intersectings'] = json_encode($intersect['intersectings']);

        foreach ($intersect as $key => $value) {
            $model->$key = $value;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            Log::error('Failed to save mountain group with id: '.$data['id'].' '.$e->getMessage());
        }
    }

    private function importNaturalSprings($model, $data)
    {
        $columnsToImport = ['id', 'code', 'loc_ref', 'source', 'source_ref', 'source_code', 'name', 'region', 'province', 'municipality', 'operator', 'type', 'volume', 'time', 'mass_flow_rate', 'temperature', 'conductivity', 'survey_date', 'lat', 'lon', 'elevation', 'note', 'geometry'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $model->$key = $value;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            Log::error('Failed to save natural spring with id: '.$data['id'].' '.$e->getMessage());
        }
    }

    private function importCaiHuts($model, $data)
    {
        $columnsToImport = ['id', 'name', 'second_name', 'description', 'elevation', 'owner', 'geometry', 'type', 'type_custodial', 'company_management_property', 'addr_street', 'addr_housenumber', 'addr_postcode', 'addr_city', 'ref_vatin', 'phone', 'fax', 'email', 'email_pec', 'website', 'facebook_contact', 'municipality_geo', 'province_geo', 'site_geo', 'opening', 'acqua_in_rifugio_serviced', 'acqua_calda_service', 'acqua_esterno_service', 'posti_letto_invernali_service', 'posti_totali_service', 'ristorante_service', 'activities', 'necessary_equipment', 'rates', 'payment_credit_cards', 'accessibilitá_ai_disabili_service', 'gallery', 'rule', 'map'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $model->$key = $value;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            Log::error('Failed to save huts with id: '.$data['id'].' '.$e->getMessage());
        }
    }

    private function importClubs($model, $data)
    {
        $columnsToImport = [
            'id', 'name', 'cai_code', 'geometry', 'website', 'email', 'phone', 'fax', 'addr_street', 'addr_housenumber', 'addr_postcode', 'addr_city', 'opening_hours', 'wheelchair',
        ];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $model->$key = $value;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Club with id: '.$data['id'].' '.$e->getMessage());
        }
    }

    private function importSectors($model, $data)
    {
        $columnsToImport = ['id', 'name', 'geometry', 'code', 'full_code', 'num_expected', 'human_name', 'manager'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $model->$key = $value;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Sector with id: '.$data['id'].' '.$e->getMessage());
        }
    }
}
