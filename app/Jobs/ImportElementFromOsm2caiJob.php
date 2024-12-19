<?php

namespace App\Jobs;

use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
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

    protected $data;

    protected $modelClass;


    /**
     * Create a new job instance.
     */
    public function __construct(string $modelClass, array $data)
    {
        $this->modelClass = $modelClass;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $modelInstance = new $this->modelClass();

        $legacyDbConnection = DB::connection('legacyosm2cai');

        if ($modelInstance->where('id', $this->data['id'])->exists()) {
            Log::info($modelInstance . ' with id: ' . $this->data['id'] . ' already imported, skipping');

            return;
        }

        $this->performImport($modelInstance, $this->data, $legacyDbConnection);
    }

    private function performImport($modelInstance, $data, $legacyDbConnection)
    {
        if ($modelInstance instanceof \App\Models\MountainGroups) {
            try {
                $this->importMountainGroups($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Mountain Group with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\EcPoi) {
            try {
                $this->importEcPois($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Ec Poi with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\NaturalSpring) {
            try {
                $this->importNaturalSprings($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Natural Spring with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\CaiHut) {
            try {
                $this->importCaiHuts($modelInstance, $data, $legacyDbConnection);
            } catch (\Exception $e) {
                Log::error('Failed to import Cai Hut with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\Club) {
            try {
                $this->importClubs($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Club with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof \App\Models\Sector) {
            try {
                $this->importSectors($modelInstance, $data, $legacyDbConnection);
            } catch (\Exception $e) {
                Log::error('Failed to import Sector with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof Area) {
            try {
                $this->importAreas($modelInstance, $data, $legacyDbConnection);
            } catch (\Exception $e) {
                Log::error('Failed to import Area with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
        if ($modelInstance instanceof HikingRoute) {
            try {
                $this->importHikingRoutes($modelInstance, $data, $legacyDbConnection);
            } catch (Exception $e) {
                Log::error('Failed to import Hiking Route with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }

        if ($modelInstance instanceof \App\Models\Itinerary) {
            try {
                $this->importItineraries($modelInstance, $data);
            } catch (\Exception $e) {
                Log::error('Failed to import Itinerary with id: ' . $data['id'] . ' ' . $e->getMessage());
            }
        }
    }

    private function importMountainGroups($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'description', 'geometry', 'aggregated_data', 'elevation_min', 'elevation_max', 'elevation_avg', 'elevation_stddev', 'slope_min', 'slope_max', 'slope_avg', 'slope_stddev'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);

        $intersect = array_intersect_key($data, array_flip($columnsToImport));
        $intersect['aggregated_data'] = json_encode($intersect['aggregated_data']);

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save mountain group with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importEcPois($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'geometry', 'osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);

        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Ec Poi with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importNaturalSprings($modelInstance, $data)
    {
        $columnsToImport = ['id', 'code', 'loc_ref', 'source', 'source_ref', 'source_code', 'name', 'region', 'province', 'municipality', 'operator', 'type', 'volume', 'time', 'mass_flow_rate', 'temperature', 'conductivity', 'survey_date', 'lat', 'lon', 'elevation', 'note', 'geometry'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);

        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save natural spring with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importCaiHuts($modelInstance, $data, $legacyDbConnection)
    {
        $columnsToImport = ['id', 'name', 'second_name', 'description', 'elevation', 'owner', 'geometry', 'type', 'type_custodial', 'company_management_property', 'addr_street', 'addr_housenumber', 'addr_postcode', 'addr_city', 'ref_vatin', 'phone', 'fax', 'email', 'email_pec', 'website', 'facebook_contact', 'municipality_geo', 'province_geo', 'site_geo', 'opening', 'acqua_in_rifugio_serviced', 'acqua_calda_service', 'acqua_esterno_service', 'posti_letto_invernali_service', 'posti_totali_service', 'ristorante_service', 'activities', 'necessary_equipment', 'rates', 'payment_credit_cards', 'accessibilitá_ai_disabili_service', 'gallery', 'rule', 'map', 'osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'region_id'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            //associate region matching the code column in legacy database
            if ($key === 'region_id') {
                $legacyRegion = $legacyDbConnection->table('regions')->find($value);
                $region = Region::where('code', $legacyRegion->code)->first();
                $value = $region ? $region->id : null;
            }
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save huts with id: ' . $data['id'] . ' ' . $e->getMessage());
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

        $data['geometry'] = $this->prepareGeometry($data['geometry']);
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        try {
            $modelInstance->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Club with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importSectors($modelInstance, $data, $legacyDbConnection)
    {
        $columnsToImport = ['id', 'name', 'geometry', 'code', 'full_code', 'num_expected', 'human_name', 'manager', 'area_id'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);

        if ($data['area_id'] !== null) {
            $legacyArea = $legacyDbConnection->table('areas')->find($data['area_id']);
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
            Log::error('Failed to save Sector with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importAreas($modelInstance, $data, $legacyDbConnection)
    {
        $columnsToImport = ['id', 'code', 'name', 'geometry', 'full_code', 'num_expected', 'province_id'];

        $data['geometry'] = $this->prepareGeometry($data['geometry']);

        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        $legacyProvince = $legacyDbConnection
            ->table('provinces')
            ->where('id', $data['province_id'])
            ->first();

        foreach ($intersect as $key => $value) {
            if ($key === 'province_id') {
                //get the code from province table in legacy osm2cai
                $provinceCode = $legacyProvince->code;
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
            Log::error('Failed to save Area with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function importHikingRoutes($modelInstance, $data, $legacyDbConnection)
    {
        $columnsToImport = ['osm2cai_status', 'validation_date', 'geometry_raw_data', 'region_favorite', 'region_favorite_publication_date', 'issues_last_update', 'issues_user_id', 'issues_cronology', 'issues_description', 'description_cai_it'];
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        $data['geometry_raw_data'] = $this->prepareGeometry($data['geometry_raw_data']);

        $hr = $modelInstance->where('osmfeatures_id', 'R' . $data['relation_id'])->first();
        if (! $hr) {
            // Write to a .txt file instead of using a log channel (error when configuring dedicated log channel)
            $logFilePath = storage_path('logs/hiking_routes_not_found.txt');
            $message = 'Hiking route not found: https://osm2cai.cai.it/resources/hiking-routes/' . $data['id'] . PHP_EOL;
            File::append($logFilePath, $message);

            return;
        }

        foreach ($intersect as $key => $value) {
            $hr->$key = $value;
        }

        try {
            $hr->save();
        } catch (\Exception $e) {
            Log::error('Failed to save Hiking_Route with id: ' . $data['id'] . ' ' . $e->getMessage());
        }
    }

    private function importItineraries($modelInstance, $data)
    {
        $columnsToImport = ['id', 'name', 'osm_id', 'ref', 'geometry'];
        $intersect = array_intersect_key($data, array_flip($columnsToImport));

        foreach ($intersect as $key => $value) {
            $modelInstance->$key = $value;
        }

        if ($data['edges'] !== null && isset($data['edges']) && ! empty($data['edges'])) {
            $modelInstance->edges = $this->recalculateEdges($data['edges']);
        }

        try {
            $modelInstance->save();
            $this->syncHikingRoutes($modelInstance, $data['id']);
        } catch (\Exception $e) {
            Log::error('Failed to save Itinerary with id: ' . $data['id'] . ' ' . $e->getMessage());
            throw $e;
        }
    }

    private function recalculateEdges($edges)
    {
        // Get all legacy hiking route IDs from edges
        $legacyIds = [];
        foreach ($edges as $edge) {
            $legacyIds = array_merge($legacyIds, $edge['next'], $edge['prev']);
        }
        $legacyIds = array_unique(array_filter($legacyIds));

        // Get relation IDs for legacy hiking routes
        $legacyHr = DB::connection('legacyosm2cai')->table('hiking_routes')
            ->whereIn('id', $legacyIds)
            ->get(['id', 'relation_id'])
            ->keyBy('id');

        // Map legacy IDs to current hiking route IDs
        $mappedEdges = array_map(function ($edge) use ($legacyHr) {
            return [
                'next' => array_map(function ($id) use ($legacyHr) {
                    if (! isset($legacyHr[$id])) {
                        return null;
                    }
                    $osmId = 'R' . $legacyHr[$id]->relation_id;
                    $hr = HikingRoute::where('osmfeatures_id', $osmId)->first();

                    return $hr ? $hr->id : null;
                }, $edge['next']),
                'prev' => array_map(function ($id) use ($legacyHr) {
                    if (! isset($legacyHr[$id])) {
                        return null;
                    }
                    $osmId = 'R' . $legacyHr[$id]->relation_id;
                    $hr = HikingRoute::where('osmfeatures_id', $osmId)->first();

                    return $hr ? $hr->id : null;
                }, $edge['prev']),
            ];
        }, $edges);

        return json_encode($mappedEdges);
    }

    private function syncHikingRoutes($modelInstance, $itineraryId)
    {
        $pivotTable = DB::connection('legacyosm2cai')->table('hiking_route_itinerary')
            ->where('itinerary_id', $itineraryId)->get();
        $hrIds = $pivotTable->pluck('hiking_route_id')->toArray();
        $legacyHr = DB::connection('legacyosm2cai')->table('hiking_routes')
            ->whereIn('id', $hrIds)
            ->get('relation_id')
            ->toArray();
        $hrIds = array_map(function ($hr) {
            return 'R' . $hr->relation_id;
        }, $legacyHr);
        $hrs = HikingRoute::whereIn('osmfeatures_id', $hrIds)->get();

        $modelInstance->hikingRoutes()->syncWithoutDetaching($hrs);
    }

    private function prepareGeometry(array $geometry)
    {
        if ($geometry !== null) {
            $geometry = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geometry) . "'), 4326)");
        }

        return $geometry;
    }
}
