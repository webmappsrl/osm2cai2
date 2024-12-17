<?php

namespace App\Nova\Actions;

use App\Models\EcPoi;
use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Symm\Gisconverter\Geometry\Point;

class ImportPois extends Action
{
    use InteractsWithQueue, Queueable;

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = HikingRoute::find($resourceId);
        }
    }

    public $name = 'IMPORT POIS';

    /**
     * Perform the action on the given models.
     *
     * @param  ActionFields  $fields
     * @param  Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $osm_ids = $this->parseOsmIds($fields->osm_ids);
        $osm_types = ['node' => 'N', 'way' => 'W', 'relation' => 'R'];

        foreach ($osm_ids as $index => $osmId) {
            $validationResult = $this->validateOsmId($osmId, $index);
            if ($validationResult !== true) {
                return $validationResult;
            }
            $typeAndId = explode('/', $osmId);
            $type = $typeAndId[0];
            $osmType = $osm_types[$type];
            $id = $typeAndId[1];
            $baseUrl = "https://api.openstreetmap.org/api/0.6/$type/$id";
            $urlTail = $type === 'node' ? '.json' : '/full.json';
            $url = $baseUrl.$urlTail;
            $abort = Action::danger("$type con ID $id non trovato. Per favore verifica l'ID e riprova.");

            try {
                $response = Http::get($url);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response->status() == 410) {
                    Log::info("OSM ID $osmId not found");

                    return $abort;
                } elseif ($e->response->status() == 404) {
                    Log::info("OSM ID $osmId not found");

                    return $abort;
                } else {
                    throw $e;
                }
            }
            $data = $response->json();
            if ($data === null) {
                Log::info("OSM ID $osmId not found");

                return $abort;
            }
            $elements = $data['elements'];
            if ($type !== 'node') {
                $coordinates = [];
                //loop over all the elements, take lat and long and calculate the centroid
                foreach ($elements as $element) {
                    if ($element['id'] != intval($id)) {
                        if ($element['type'] === 'node') {
                            $coordinates[] = [$element['lon'], $element['lat']];
                        }
                    } else {
                        $poi = EcPoi::updateOrCreate(['osmfeatures_id' => $osmType.$element['id']], [
                            'name' => $element['tags']['name'] ?? $element['tags']['name:it'] ?? 'no name ('.$type.'/'.$element['id'].')',
                            'geometry' => null,
                            'tags' => $element['tags'] ?? null,
                            'user_id' => auth()->user()->id,
                        ]);
                    }
                }
                //get the centroid using the coordinates array
                $centroidCoords = $this->calculateCentroid($coordinates);
                $poi->geometry = DB::raw("ST_SetSRID(ST_MakePoint({$centroidCoords[0]}, {$centroidCoords[1]}), 4326)");
                $poi->save();
            } else {
                foreach ($elements as $element) {
                    if ($element['type'] !== 'node') {
                        continue;
                    }
                    $this->importPoi($element, $osmType);
                }
            }
        }

        return Action::message('Import completato');
    }

    private function parseOsmIds($osm_ids_string)
    {
        $osm_ids = explode(',', str_replace(' ', '', $osm_ids_string));

        return array_unique($osm_ids);
    }

    private function importPoi($data, $osmType)
    {
        $osmId = $data['id'];
        $name = $data['name'] ?? $data['tags']['name'] ?? $data['tags']['name:it'] ?? 'no name ('.$data['id'].')';
        $geometry = DB::raw("ST_SetSRID(ST_MakePoint({$data['lon']}, {$data['lat']}), 4326)");
        $tags = $data['tags'] ?? null;

        $poi = EcPoi::updateOrCreate(['osmfeatures_id' => $osmType.$osmId], [
            'name' => $name,
            'geometry' => $geometry,
            'tags' => $tags,
            'user_id' => auth()->user()->id,
        ]);
    }

    private function calculateCentroid($coordinates)
    {
        $sumLat = 0;
        $sumLon = 0;
        $count = count($coordinates);
        foreach ($coordinates as $coordinate) {
            $sumLon += $coordinate[0];
            $sumLat += $coordinate[1];
        }

        $centroidLon = $sumLon / $count;
        $centroidLat = $sumLat / $count;

        $centroid = [$centroidLon, $centroidLat];

        return $centroid;
    }

    private function validateOsmId($osmId, $index)
    {
        $dangerMessage = "ID $osmId non valido alla posizione ".($index + 1)."'. Per favore verifica l'ID e riprova. Assicurati che dopo ogni ID ci sia una virgola e non mettere la virgola dopo l'ultimo ID.";
        if (strpos($osmId, '/') === false) {
            return Action::danger($dangerMessage);
        }
        if (strpos($osmId, 'node') === false && strpos($osmId, 'way') === false && strpos($osmId, 'relation') === false) {
            return Action::danger($dangerMessage);
        }
        if (strlen($osmId) < 1) {
            return false;
        }

        return true;
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [
            Textarea::make('OSM IDs', 'osm_ids')->help('Inserisci gli ID OSM separati da virgola. Esempio: node/123456,way/123456,relation/123456'),
        ];
    }
}
