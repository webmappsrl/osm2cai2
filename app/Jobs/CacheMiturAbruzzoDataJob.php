<?php

namespace App\Jobs;

use App\Enums\WikiImageType;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CacheMiturAbruzzoDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;

    protected $modelId;

    protected $isTestEnvironment;

    public function __construct($model, $modelId, $isTestEnvironment = false)
    {
        $this->model = $model;
        $this->modelId = $modelId;
        $this->isTestEnvironment = $isTestEnvironment;
    }

    protected function logger()
    {
        return Log::channel('mitur-cache');
    }

    public function handle()
    {
        $modelClass = "App\\Models\\{$this->model}";
        $model = $modelClass::find($this->modelId);

        if (! $model) {
            $this->logger()->error("Model {$this->model} with ID {$this->modelId} not found");

            return;
        }

        $this->processModel($model);
    }

    protected function processModel($model)
    {
        $className = class_basename($model);
        $this->logger()->info("Processing {$className} with ID {$model->id}");

        $geojson = $this->buildGeojsonForModel($model);

        if ($geojson) {
            if ($this->isTestEnvironment) {
                $this->saveLocally($geojson, $className, $model->id);
            } else {
                $model->cacheDataToAws($geojson, 'wmfemitur');
                $this->logger()->info("Cached MITUR data for {$className} {$model->id}");
            }
        }
    }

    protected function saveLocally($geojson, $className, $modelId)
    {
        $path = "tests/stubs/mitur/aws/{$className}_{$modelId}.json";

        Storage::makeDirectory(dirname($path));
        Storage::put($path, json_encode($geojson));

        $this->logger()->info("Saved MITUR data locally for {$className} {$modelId} at {$path}");
    }

    protected function buildGeojsonForModel($model): ?array
    {
        switch (get_class($model)) {
            case HikingRoute::class:
                return $this->buildHikingRouteGeojson($model);
            case MountainGroups::class:
                return $this->buildMountainGroupGeojson($model);
            case Club::class:
                return $this->buildClubGeojson($model);
            case EcPoi::class:
                return $this->buildEcPoiGeojson($model);
            case CaiHut::class:
                return $this->buildCaiHutGeojson($model);
            case Region::class:
                return $this->buildRegionGeojson($model);
            default:
                $this->logger()->error('Unsupported model type: ' . get_class($model));
                throw new \Exception('Unsupported model type: ' . get_class($model));
        }
    }

    protected function buildHikingRouteGeojson($hikingRoute): array
    {
        $ecPoiModel = new EcPoi();
        $intersectingPois = $hikingRoute->getIntersections($ecPoiModel);

        $pois = $intersectingPois->pluck('updated_at', 'id')->toArray();

        $tdh = $hikingRoute->tdh;

        $geometry = $hikingRoute->getGeometryGeojson();

        $points = $this->getFromAndToPoints($geometry);
        $fromPoint = $points['from'];
        $toPoint = $points['to'];

        //get the cai huts intersecting with the hiking route
        $huts = $hikingRoute->nearbyCaiHuts;
        $caiHuts = [];
        //transform the huts array into an associative array where the key is hut id and value is the hut updated_at
        if (! empty($huts)) {
            foreach ($huts as $hut) {
                $caiHuts[$hut] = CaiHut::find($hut)->updated_at;
            }
        }

        //get the sections associated with the hiking route
        $clubsIds = $hikingRoute->clubs->pluck('updated_at', 'id')->toArray();

        // get the abstract from the hiking route and get only it description
        $abstract = $hikingRoute->tdh['abstract']['it'] ?? '';

        //get the difficulty based on cai_scale value

        switch ($hikingRoute->osmfeatures_data['properties']['cai_scale'] ?? '') {
            case 'T':
                $difficulty = 'Turistico';
                break;
            case 'E':
                $difficulty = 'Escursionistico';
                break;
            case 'EE':
                $difficulty = 'Escursionistico per Esperti';
                break;
            case 'EEA':
                $difficulty = 'Escursionistco per Esperti con Attrezzatura';
                break;
            case 'EEA:F':
                $difficulty = 'Escursionistco per Esperti con Attrezzatura';
                break;
            case 'EEA:D':
                $difficulty = 'Escursionistco per Esperti con Attrezzatura';
                break;
            case 'EEA:MD':
                $difficulty = 'Escursionistco per Esperti con Attrezzatura';
                break;
            case 'EEA:E':
                $difficulty = 'Escursionistco per Esperti con Attrezzatura';
                break;
            default:
                $difficulty = 'Non definito';
        }

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';

        $properties = [];
        $properties['id'] = $hikingRoute->id;
        $properties['ref'] = $hikingRoute->osmfeatures_data['properties']['ref'] ?? '';
        $properties['name'] = $hikingRoute->name ?? '';
        $properties['abstract'] = $abstract;
        $properties['info'] = 'Sezioni del Club Alpino Italiano, Guide Alpine o Guide Ambientali Escursionistiche';
        $properties['activity'] = 'Escursionismo';
        $properties['map'] = route('hiking-route-public-page', ['id' => $hikingRoute->id]);
        $properties['gpx_url'] = $tdh['gpx_url'] ?? '';
        $properties['images'] = [];

        $properties['symbol'] = 'Segnaletica standard CAI';
        $properties['cai_scale'] = $hikingRoute->osmfeatures_data['properties']['cai_scale'] ?? '';
        $properties['difficulty'] = $difficulty;
        $properties['from'] = $hikingRoute->osmfeatures_data['properties']['from'] ?? '';
        $properties['to'] = $hikingRoute->osmfeatures_data['properties']['to'] ?? '';
        $properties['from:coordinate'] = $fromPoint;
        $properties['to:coordinate'] = $toPoint;
        $properties['distance'] = $hikingRoute->tdh['distance'] ?? 100;
        $properties['duration_forward'] = $tdh['duration_forward'] ?? '';
        $properties['duration_backward'] = $tdh['duration_backward'] ?? '';
        $properties['ele_max'] = $tdh['ele_max'] ?? '';
        $properties['ele_min'] = $tdh['ele_min'] ?? '';
        $properties['ele_from'] = $tdh['ele_from'] ?? '';
        $properties['ele_to'] = $tdh['ele_to'] ?? '';
        $properties['ascent'] = $tdh['ascent'] ?? '';
        $properties['descent'] = $tdh['descent'] ?? '';
        $properties['issues_status'] = $hikingRoute->issues_status;

        $properties['section_ids'] = $clubsIds ?? [];
        $properties['cai_huts'] = $caiHuts;
        $properties['pois'] = count($pois) > 0 ? $pois->pluck('updated_at', 'id')->toArray() : [];

        $geojson['properties'] = $properties;
        $geojson['geometry'] = $geometry;

        return $geojson;
    }

    protected function buildMountainGroupGeojson($mountainGroup): array
    {
        $regions = $mountainGroup->regions->pluck('name')->implode(', ');
        $provinces = $mountainGroup->getIntersections(new Province())->pluck('name')->implode(', ');
        $municipalities = $mountainGroup->getIntersections(new Municipality())->pluck('comune')->implode(', ');

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';

        $properties = [];
        $properties['id'] = $mountainGroup->id;
        $properties['name'] = $mountainGroup->name ?? 'Nome del gruppo Montuoso';
        $properties['description'] = $mountainGroup->description ?? '';
        $properties['map'] = route('mountain-groups-map', ['id' => $mountainGroup->id]);
        $properties['hiking_routes_map'] = route('mountain-groups-hr-map', ['id' => $mountainGroup->id]);
        $properties['images'] = [];
        $properties['activity'] = 'Escursionismo';
        $properties['region'] = $regions;
        $properties['provinces'] = $provinces;
        $properties['municipalities'] = $municipalities;
        $properties['disclaimer'] = 'L’escursionismo e, più in generale, l’attività all’aria aperta, è una attività potenzialmente rischiosa: prima di avventurarti in una escursione assicurati di avere le conoscenze e le competenze per farlo. Se non sei sicuro rivolgiti agli esperti locali che ti possono aiutare, suggerire e supportare nella pianificazione e nello svolgimento delle tue attività. I dati non possono garantire completamente la percorribilità senza rischi dei percorsi: potrebbero essersi verificati cambiamenti, anche importanti, dall’ultima verifica effettuata del percorso stesso. E’ fondamentale quindi che chi si appresta a svolgere attività valuti attentamente l’opportunità di proseguire in base ai suggerimenti e ai consigli contenuti, in base alla propria esperienza, alle condizioni metereologiche (anche dei giorni precedenti) e di una valutazione effettuata sul campo all’inizio dello svolgimento della attività. Il Club Alpino Italiano non fornisce garanzie sulla sicurezza dei luoghi descritti, e non si assume alcuna responsabilità per eventuali danni causati dallo svolgimento delle attività descritte.';

        $properties['area'] = $mountainGroup->getArea();
        $properties['ele_min'] = $mountainGroup->elevation_min ?? '';
        $properties['ele_max'] = $mountainGroup->elevation_max ?? '';
        $properties['ele_avg'] = $mountainGroup->elevation_avg ?? '';
        $properties['ele_stddev'] = $mountainGroup->elevation_stddev ?? '';
        $properties['slope_min'] = $mountainGroup->slope_min ?? '';
        $properties['slope_max'] = $mountainGroup->slope_max ?? '';
        $properties['slope_avg'] = $mountainGroup->slope_avg ?? '';
        $properties['slope_stddev'] = $mountainGroup->slope_stddev ?? '';

        //TODO: check IDs are correct or recalculate the intersections
        $properties['section_ids'] = $mountainGroup->clubs->pluck('updated_at', 'id')->toArray();
        $properties['hiking_routes'] = $mountainGroup->hikingRoutes->where('osm2cai_status', 4)->pluck('updated_at', 'id')->toArray();
        $properties['ec_pois'] = $mountainGroup->ecPois->pluck('updated_at', 'id')->toArray();
        $properties['cai_huts'] = $mountainGroup->caiHuts->pluck('updated_at', 'id')->toArray();

        $geojson['properties'] = $properties;
        $geojson['geometry'] = $mountainGroup->getGeometryGeojson();

        return $geojson;
    }

    protected function buildClubGeojson($club): array
    {
        $this->logger()->info("Start caching club $club->name");

        $queryForProvinces = <<<'SQL'
SELECT 
    p.id AS province_id, 
    p.name AS province_name, 
    c.id AS club_id, 
    c.name AS club_name
FROM 
    clubs c
JOIN 
    provinces p 
ON 
    ST_Intersects(ST_Transform(p.geometry, 4326), c.geometry)
SQL;

        $provinces = DB::select($queryForProvinces);
        //get the province names

        $provincesNames = [];
        foreach ($provinces as $province) {
            $provincesNames[] = $province->province_name;
        }
        //delete double provinces

        $provincesNames = array_unique($provincesNames);

        //implode the provinces
        $provincesNames = implode(', ', $provincesNames);

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';

        $properties = [];
        $properties['id'] = $club->id;
        $properties['name'] = $club->name;
        $properties['addr:city'] = $club->addr_city ?? '';
        $properties['addr:housenumber'] = $club->addr_housenumber ?? '';
        $properties['addr:postcode'] = $club->addr_postcode ?? '';
        $properties['addr:street'] = $club->addr_street ?? '';
        $properties['provinces'] = $provincesNames;
        $properties['source:ref'] = $club->cai_code;
        $properties['website'] = $club->website ?? '';
        $properties['email'] = $club->email ?? '';
        $properties['opening_hours'] = $club->opening_hours ?? '';
        $properties['phone'] = $club->phone ?? '';
        $properties['wheelchair'] = $club->wheelchair ?? '';
        $properties['fax'] = $club->fax ?? '';
        $properties['images'] = [];

        $geometry = $club->getGeometryGeojson();

        $geojson['properties'] = $properties;
        $geojson['geometry'] = $geometry;

        return $geojson;
    }

    protected function buildEcPoiGeojson($poi): array
    {
        $this->logger()->info("Start caching poi $poi->name");
        $osmfeaturesData = $this->extractOsmfeaturesData($poi);
        $enrichmentsData = $this->extractEnrichmentsData($osmfeaturesData);

        $type = '';

        if (isset($osmfeaturesData['class'])) {
            $type = $osmfeaturesData['class'];
        }
        if (isset($osmfeaturesData['subclass'])) {
            $type .= '/' . $osmfeaturesData['subclass'];
        }

        $images = $this->getImagesFromOsmfeaturesData($enrichmentsData);

        //get only hiking routes in a 1000m buffer with osm2cai status 4
        $hikingRoutesInBuffer = $poi->getElementsInBuffer(new HikingRoute(), 1000)->where('osm2cai_status', 4);

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';

        $intersectingMunicipalities = $poi->getIntersections(new Municipality());
        $municipalities = $intersectingMunicipalities->pluck('comune')->implode(', ');

        $properties = [];
        $properties['id'] = $poi->id;
        $properties['name'] = $osmfeaturesData['name'] ?? $poi->name;
        $properties['type'] = $type ?? '';
        $properties['info'] = $enrichmentsData['abstract']['it'] ?? '';
        $properties['description'] = $enrichmentsData['description']['it'] ?? '';
        $properties['map'] = route('poi-map', ['id' => $poi->id]);
        $properties['images'] = $images ?? [];
        $properties['comune'] = $municipalities;
        $properties['difficulty'] = $hikingRoutesInBuffer->first() ? $hikingRoutesInBuffer->first()->osmfeatures_data['properties']['cai_scale'] ?? '' : '';
        $properties['activity'] = 'Escursionismo';
        $properties['has_hiking_routes'] = $hikingRoutesInBuffer->pluck('updated_at', 'id')->toArray() ?? [];

        $geometry = $poi->getGeometryGeojson();

        $geojson['properties'] = $properties;
        $geojson['geometry'] = $geometry;

        return $geojson;
    }

    protected function buildRegionGeojson($region): array
    {
        $this->logger()->info("Start caching region $region->name");

        //get the mountain groups for the region
        $mountainGroups = $region->mountainGroups;
        //format the date
        $mountainGroups = $mountainGroups->mapWithKeys(function ($mountainGroup) {
            $formattedDate = $mountainGroup->updated_at ? $mountainGroup->updated_at->toIso8601String() : null;

            return [$mountainGroup->id => $formattedDate];
        });

        $geom = $region->getGeometryGeojson();

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';
        $geojson['properties'] = [];
        $geojson['geometry'] = $geom;

        $properties = [];
        $properties['id'] = $region->id;
        $properties['name'] = $region->name ?? '';
        $properties['mountain_groups'] = $mountainGroups;

        $geojson['properties'] = $properties;

        return $geojson;
    }

    protected function buildCaiHutGeojson($hut): array
    {
        $this->logger()->info("Start caching hut $hut->id");

        //get the mountain groups for the hut based on the geometry intersection
        $mountainGroups = $hut->mountainGroups->first();

        //get the pois in a 1km buffer from the hut
        $pois = $hut->getElementsInBuffer(new EcPoi(), 1000);

        //get the hiking routes in a 1km buffer from the hut
        $hikingRoutes = $hut->getElementsInBuffer(new HikingRoute(), 1000);

        //get osmfeatures data
        $osmfeaturesData = $this->extractOsmfeaturesData($hut);
        $enrichmentsData = $this->extractEnrichmentsData($osmfeaturesData);

        //get images from Osmfeatures
        $images = $this->getImagesFromOsmfeaturesData($enrichmentsData);

        //build the geojson
        $geojson = [];
        $geojson['type'] = 'Feature';

        $properties = [];
        $properties['id'] = $hut->id;
        $properties['name'] = $hut->second_name ?? $hut->name ?? '';
        $properties['type'] = explode(' ', $hut->second_name)[0] ?? '';
        $properties['abstract'] = $enrichmentsData['abstract']['it'] ?? '';
        $properties['description'] = $enrichmentsData['description']['it'] ?? '';
        $properties['map'] = route('cai-huts-map', ['id' => $hut->id]);
        $properties['images'] = $images ?? [];

        $properties['elevation'] = $hut->elevation ?? '';
        $properties['type_custodial'] = $hut->type_custodial ?? '';
        $properties['company_management_property'] = $hut->company_management_property ?? '';
        $properties['addr:street'] = $hut->addr_street ?? '';
        $properties['addr:housenumber'] = $hut->addr_housenumber ?? '';
        $properties['addr:postcode'] = $hut->addr_postcode ?? '';
        $properties['addr:city'] = $hut->addr_city ?? '';
        $properties['ref:vatin'] = $hut->ref_vatin ?? '';
        $properties['phone'] = $hut->phone ?? '';
        $properties['fax'] = $hut->fax ?? '';
        $properties['email'] = $hut->email ?? '';
        $properties['email_pec'] = $hut->email_pec ?? '';
        $properties['website'] = $hut->website ?? '';
        $properties['facebook_contact'] = $hut->facebook_contact ?? '';
        $properties['municipality_geo'] = $hut->municipality_geo ?? '';
        $properties['province_geo'] = $hut->province_geo ?? '';
        $properties['site_geo'] = $hut->site_geo ?? '';
        $properties['source:ref'] = $hut->unico_id;
        $properties['pois'] = $pois->count() > 0 ? $pois->pluck('updated_at', 'id')->toArray() : [];
        $properties['opening'] = $hut->opening ?? '';
        $properties['acqua_in_rifugio_service'] = $hut->acqua_in_rifugio_serviced ?? '';
        $properties['acqua_calda_service'] = $hut->acqua_calda_service ?? '';
        $properties['acqua_esterno_service'] = $hut->acqua_esterno_service ?? '';
        $properties['posti_letto_invernali_service'] = $hut->posti_letto_invernali_service ?? '';
        $properties['posti_totali_service'] = $hut->posti_totali_service ?? '';
        $properties['ristorante_service'] = $hut->ristorante_service ?? '';
        $properties['activity'] = $hut->activities ?? 'Escursionismo,Alpinismo';
        $properties['necessary_equipment'] = $hut->necessary_equipment ?? 'Normale dotazione Escursionistica / Normale dotazione Alpinistica';
        $properties['rates'] = $hut->rates ?? 'https://www.cai.it/wp-content/uploads/2024/07/23-2022-Circolare-Tariffario-rifugi-2023_signed.pdf';
        $properties['payment_credit_cards'] = $hut->payment_credit_cards ?? '1';
        $properties['accessibilitá_ai_disabili_service'] = $hut->acessibilitá_ai_disabili_service ?? '';
        $properties['rule'] = $hut->rule ?? 'https://www.cai.it/wp-content/uploads/2024/07/Regolamento-strutture-ricettive-del-Club-Alpino-Italiano-20201.pdf';

        $properties['hiking_routes'] = $hikingRoutes->count() > 0 ? $hikingRoutes->pluck('updated_at', 'id')->toArray() : [];
        $properties['mountain_groups'] = $mountainGroups ? $mountainGroups->id : '';

        // Check if hut has osmfeatures_id, if not, add abstract and images manually
        if ($properties['abstract'] == '') {
            $regionName = $hut->region ? $hut->region->name : '';
            $elevation = $hut->elevation ?? '';

            //build abstract
            $properties['abstract'] = "{$hut->second_name} è una struttura gestita dal Club Alpino Italiano";

            if ($elevation) {
                $properties['abstract'] .= " situata a quota {$elevation} mslm";
            }

            if ($regionName) {
                $properties['abstract'] .= ", nella regione {$regionName}";
            }

            $properties['abstract'] .= '.';
        }

        if ($properties['images'] == []) {
            // Perform API call to rifugi.cai.it to get image
            $this->logger()->info("Performing rifugi API call for hut $hut->id");
            $shelterResponse = Http::get("https://rifugi.cai.it/api/v1/shelters?attributes%5Bid%5D={$hut->unico_id}");
            if ($shelterResponse && isset($shelterResponse['data'][0]['id_cai'])) {
                $idCai = $shelterResponse['data'][0]['id_cai'];
                $shelterDetailsResponse = Http::get("https://rifugi.cai.it/api/v1/shelters/{$idCai}");
                if ($shelterDetailsResponse && ! empty($shelterDetailsResponse['media'])) {
                    foreach ($shelterDetailsResponse['media'] as $image) {
                        $images[] = $image['original_url'];
                    }
                    if (count($images) < 1) {
                        $this->logger()->info("No images found for hut $hut->id in rifugi API https://rifugi.cai.it/api/v1/shelters/{$idCai}");
                    }
                    $properties['images'] = $images;
                    $this->logger()->info("Retrieved images from rifugi api for hut $hut->id");
                }
            }
        }

        // check if there are duplicated images
        if (count($properties['images']) > 0) {
            $properties['images'] = array_unique($properties['images']);
        }

        $geometry = $hut->getGeometryGeojson();

        $geojson['properties'] = $properties;
        $geojson['geometry'] = $geometry;

        return $geojson;
    }

    protected function extractOsmfeaturesData(Model $model)
    {
        $modelClass = get_class($model);
        if (! $model->osmfeatures_data) {
            $this->logger()->info("No osmfeatures data for $modelClass $model->name");
            $osmfeaturesData = [];
        } else {
            $osmfeaturesData = is_string($model->osmfeatures_data) ? json_decode($model->osmfeatures_data, true) : $model->osmfeatures_data;
        }

        return $osmfeaturesData;
    }

    protected function extractEnrichmentsData(array $osmfeaturesData): array
    {
        return $osmfeaturesData['enrichments']['data'] ?? [];
    }

    protected function getImagesFromOsmfeaturesData($enrichmentsData)
    {
        $this->logger()->info('Start getting images from osmfeatures data');
        $images = [];
        if (! isset($enrichmentsData['images'])) {
            return $images;
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        //extend the allowed extension to double the values but uppercase
        $allowedExtensions = array_merge($allowedExtensions, array_map('strtoupper', $allowedExtensions));

        foreach (WikiImageType::cases() as $imageType) {
            if (isset($enrichmentsData['images'][$imageType->value])) {
                $imageData = $enrichmentsData['images'][$imageType->value];

                if ($imageType == WikiImageType::WIKIMEDIA_IMAGES) {
                    //can be more than one image
                    foreach ($imageData as $image) {
                        if (isset($image['source_url']) && in_array(pathinfo($image['source_url'], PATHINFO_EXTENSION), $allowedExtensions)) {
                            $images[] = $image['source_url'];
                        }
                    }
                }

                if (isset($imageData['source_url']) && in_array(pathinfo($imageData['source_url'], PATHINFO_EXTENSION), $allowedExtensions)) {
                    $images[] = $imageData['source_url'];
                }
            }
        }

        $this->logger()->info('End getting images from osmfeatures data');

        return $images;
    }

    protected function getFromAndToPoints($geometry)
    {
        $points = $geometry['coordinates'][0];
        $fromPoint = implode(',', $points[0]);
        $toPoint = implode(',', $points[count($points) - 1]);

        return ['from' => $fromPoint, 'to' => $toPoint];
    }
}
