<?php

namespace App\Jobs;

use App\Models\Area;
use App\Models\Province;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportElementFromOsm2caiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

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
        $response = Http::get($this->apiUrl);

        if ($response->failed()) {
            Log::error('Failed to retrieve data from OSM2CAI API'.$response->body());

            return;
        }

        $data = $response->json();

        $modelInstance = new $this->modelClass();

        if ($modelInstance->where('id', $data['id'])->exists()) {
            Log::info($modelInstance.' with id: '.$data['id'].' already imported, skipping');

            return;
        }

        $this->performImport($modelInstance, $data);
    }

    private function performImport($modelInstance, $data)
    {
        if ($modelInstance instanceof \App\Models\MountainGroups) {
            try {
                $this->importMountainGroups($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Mountain Group with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\EcPoi) {
            try {
                $this->importEcPois($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Ec Poi with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\NaturalSpring) {
            try {
                $this->importNaturalSprings($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Natural Spring with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\CaiHut) {
            try {
                $this->importCaiHuts($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Cai Hut with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\Club) {
            try {
                $this->importClubs($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Club with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\Sector) {
            try {
                $this->importSectors($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Sector with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof Area) {
            try {
                $this->importAreas($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Area with id: '.$data['id'].' '.$e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\HikingRoute) {
            try {
                $this->importHikingRoutes($modelInstance, $data);
            } catch (Exception $e) {
                Log::error('Failed to import Hiking Route with id: '.$data['id'].' '.$e->getMessage());
            }
        }

        if ($modelInstance instanceof \App\Models\Itinerary) {
            try {
                $this->importItineraries($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Itinerary with id: '.$data['id'].' '.$e->getMessage());
            }
        }
    }

    private function importMountainGroups($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'description', 'geometry', 'aggregated_data', 'intersectings', 'elevation_min', 'elevation_max', 'elevation_avg', 'elevation_stddev', 'slope_min', 'slope_max', 'slope_avg', 'slope_stddev'];

        $data['intersectings'] = [
            'hiking_routes' => json_decode($data['hiking_routes_intersecting'], true),
            'clubs' => json_decode($data['sections_intersecting'], true),
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
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save mountain group with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importEcPois($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'geometry', 'osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Ec Poi with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importNaturalSprings($modelInstance, $data)
    {
        $columnsToImport = ['id', 'code', 'loc_ref', 'source', 'source_ref', 'source_code', 'name', 'region', 'province', 'municipality', 'operator', 'type', 'volume', 'time', 'mass_flow_rate', 'temperature', 'conductivity', 'survey_date', 'lat', 'lon', 'elevation', 'note', 'geometry'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save natural spring with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importCaiHuts($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'second_name', 'description', 'elevation', 'owner', 'geometry', 'type', 'type_custodial', 'company_management_property', 'addr_street', 'addr_housenumber', 'addr_postcode', 'addr_city', 'ref_vatin', 'phone', 'fax', 'email', 'email_pec', 'website', 'facebook_contact', 'municipality_geo', 'province_geo', 'site_geo', 'opening', 'acqua_in_rifugio_serviced', 'acqua_calda_service', 'acqua_esterno_service', 'posti_letto_invernali_service', 'posti_totali_service', 'ristorante_service', 'activities', 'necessary_equipment', 'rates', 'payment_credit_cards', 'accessibilitá_ai_disabili_service', 'gallery', 'rule', 'map', 'osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save huts with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importClubs($modelInstance, $data)
    {
        $columnsToImport = [
            'id',
            'name',
            'cai_code',
            'geometry',
            'website',
            'email',
            'phone',
            'fax',
            'addr_street',
            'addr_housenumber',
            'addr_postcode',
            'addr_city',
            'opening_hours',
            'wheelchair',
        ];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Club with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importSectors($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'geometry', 'code', 'full_code', 'num_expected', 'human_name', 'manager', 'area_id'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326))");
        }

        if ($data['area_id'] !== null) {
            $legacyArea = DB::connection('legacyosm2cai')->table('areas')->find($data['area_id']);
            $area = Area::where('full_code', $legacyArea->full_code)->first();
            $data['area_id'] = $area->id ?? null;
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Sector with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importAreas($modelInstance, $data)
    {
        $columnsToImport = ['id', 'code', 'name', 'geometry', 'full_code', 'num_expected', 'province_id'];

        if ($data['geometry'] !== null) {
            $data['geometry'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry'])."'), 4326)");
        }
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            if ($key === 'province_id') {
                //get the code from province table in legacy osm2cai
                $province = DB::connection('legacyosm2cai')->table('provinces')->find($value);
                $provinceCode = $province->code;
                //search for the corresponding province in the province table
                $province = Province::where('osmfeatures_data->properties->osm_tags->short_name', $provinceCode)
                    ->orWhere('osmfeatures_data->properties->osm_tags->ref', $provinceCode)
                    ->first();
                $value = $province ? $province->id : null;
            }
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Area with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }

    private function importHikingRoutes($modelInstance, $data)
    {
        $columnsToImport = ['osm2cai_status', 'validation_date', 'geometry_raw_data', 'region_favorite', 'region_favorite_publication_date', 'issues_last_update', 'issues_user_id', 'issues_cronology', 'issues_description', 'description_cai_it'];
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        if ($data['geometry_raw_data'] !== null) {
            $data['geometry_raw_data'] = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($data['geometry_raw_data'])."'), 4326)");
        }

        $hr = $modelInstance->where('osmfeatures_id', 'R'.$data['relation_id'])->first();
        if (! $hr) {
            // Write to a .txt file instead of using a log channel (error when configuring dedicated log channel)
            $logFilePath = storage_path('logs/hiking_routes_not_found.txt');
            $message = 'Hiking route not found: https://osm2cai.cai.it/resources/hiking-routes/'.$data['id'].PHP_EOL;
            File::append($logFilePath, $message);

            return;
        }

        foreach ($intersect as $key => $value) {
            $hr->$key = $value;
        }

        try {
            $hr->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Hiking_Route with id: '.$data['id'].' '.$e->getMessage());
        }
    }

    private function importItineraries($modelInstance, $data)
    {
        $columnsToImport = ['name', 'edges', 'osm_id', 'ref', 'geometry'];
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Itinerary with id: '.$data['id'].' '.$e->getMessage());
            throw $e;
        }
    }
}
