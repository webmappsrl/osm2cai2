<?php

namespace App\Services;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symm\Gisconverter\Exceptions\InvalidText;
use Symm\Gisconverter\Gisconverter;

class GeometryService
{
    /**
     * Return an istance of this class
     *
     * @return GeometryService
     */
    public static function getService()
    {
        return app(__CLASS__);
    }

    public function geojsonToGeometry($geojson)
    {
        if (empty($geojson)) {
            return null;
        }

        if (is_array($geojson)) {
            $geojson = json_encode($geojson);
        }

        return DB::select("select (ST_Force3D(ST_GeomFromGeoJSON('" . $geojson . "'))) as g ")[0]->g;
    }

    /**
     * Convert geojson in MULTILINESTRING postgis geometry with correct SRID
     *
     * @param  string  $geojson
     * @return string - the postgis geometry in string format
     */
    public function geojsonToMultilinestringGeometry($geojson)
    {
        return DB::select("select (
        ST_Multi(
          ST_GeomFromGeoJSON('" . $geojson . "')
        )
    ) as g ")[0]->g;
    }

    /**
     * Convert geojson in MULTILINESTRING postgis geometry with 3857 SRID
     *
     * @param  string  $geojson
     * @return string - the postgis geometry in string format
     */
    public function geojsonToMultilinestringGeometry3857($geojson)
    {
        return DB::select("select (
        ST_Multi(
          ST_Transform( ST_GeomFromGeoJSON('" . $geojson . "' ) , 3857 )
        )
    ) as g ")[0]->g;
    }

    public function geometryTo4326Srid($geometry)
    {
        return DB::select("select (
      ST_Transform('" . $geometry . "', 4326)
    ) as g ")[0]->g;
    }

    public function textToGeojson($text)
    {
        $contentGeometry = $contentType = null;
        if ($text) {
            if (strpos($text, '<?xml') !== false && strpos($text, '<?xml') < 10) {
                $geojson = '';
                if ($geojson === '') {
                    try {
                        $geojson = Gisconverter::gpxToGeojson($text);
                        $content = json_decode($geojson, true);
                        $contentType = $content['type'];
                    } catch (InvalidText $ec) {
                    }
                }

                if ($geojson === '') {
                    try {
                        $geojson = Gisconverter::kmlToGeojson($text);
                        $content = json_decode($geojson, true);
                        $contentType = $content['type'];
                    } catch (InvalidText $ec) {
                    }
                }
            } else {
                $content = json_decode($text, true);
                $isJson = json_last_error() === JSON_ERROR_NONE;
                if ($isJson) {
                    $contentType = $content['type'];
                }
            }

            if ($contentType) {
                switch ($contentType) {
                    case 'GeometryCollection':
                        foreach ($content['geometries'] as $item) {
                            if ($item['type'] == 'LineString') {
                                $contentGeometry = $item;
                            }
                        }
                        break;
                    case 'FeatureCollection':
                        $contentGeometry = $content['features'][0]['geometry'];
                        break;
                    case 'LineString':
                        $contentGeometry = $content;
                        break;
                    default:
                        $contentGeometry = $content['geometry'];
                        break;
                }
            }
        }

        return $contentGeometry;
    }

    /**
     * Get the geometry type of the given model.
     *
     * @param  string  $table  The name of the table.
     * @param  string  $geometryColumn  The name of the geometry column.
     * @return string
     */
    public static function getGeometryType(string $table, string $geometryColumn)
    {
        // Costruire la query per determinare il tipo di geometria
        // Nota: Per le colonne di tipo geography, dobbiamo fare un cast a geometry
        // prima di usare ST_GeometryType()
        $query = <<<SQL
        SELECT 
            ST_GeometryType({$geometryColumn}::geometry) AS geom_type
        FROM {$table}
        WHERE {$geometryColumn} IS NOT NULL
        LIMIT 1;
        SQL;

        // Eseguire la query e ottenere il tipo di geometria
        $type = DB::selectOne($query);

        // Restituire il tipo di geometria senza il prefisso "ST_"
        return $type ? str_replace('ST_', '', $type->geom_type) : 'Unknown';
    }

    public function getCentroid($geometry)
    {
        if (empty($geometry)) {
            return null;
        }

        $geometry = $this->geojsonToGeometry($geometry);

        return DB::select("select ST_AsGeoJSON(ST_Centroid('" . $geometry . "')) as g")[0]->g;
    }

    /**
     * Get intersections between a base model and intersecting model.
     * Intersezione precisa sulla geometria originale (minimizza i falsi positivi, anche su tracce oblique).
     * Trade-off: più lenta rispetto ad approcci approssimati (es. bbox), ma più corretta.
     * ST_Intersects supporta sia geometry che geography; con geography usa tolleranza 0.00001 m per punti vicini.
     *
     * @param \Illuminate\Database\Eloquent\Model $baseModel The model to calculate intersections from
     * @param string|\Illuminate\Database\Eloquent\Model $intersectingModelClass The class name or instance of the model to find intersections with
     * @return \Illuminate\Support\Collection Collection of intersecting models
     */
    public static function getIntersections(Model $baseModel, $intersectingModelClass): Collection
    {
        // Gestire sia stringa che istanza Model
        if (is_string($intersectingModelClass)) {
            $intersectingModel = new $intersectingModelClass;
        } elseif ($intersectingModelClass instanceof Model) {
            $intersectingModel = $intersectingModelClass;
        } else {
            throw new \Exception('Intersecting model class must be a string or Model instance');
        }

        try {
            $baseTable = $baseModel->getTable();
            $baseId = $baseModel->id;
            $intersectingTable = $intersectingModel->getTable();
            // Intersezione precisa sulla geometria originale (minimizza i falsi positivi, anche su tracce oblique).
            // Trade-off: più lenta rispetto ad approcci approssimati (es. bbox), ma più corretta.
            // Usa geometry invece di geography per compatibilità con colonne di tipo geography nel DB.
            // Il cast a geometry funziona sia per colonne geometry che geography.

            // Usa una CTE (Common Table Expression) per recuperare la geometria base una sola volta
            // Questo evita di ripetere la subquery e rende la query più efficiente
            $query = "WITH base_geometry AS (
                     SELECT geometry::geometry as geometry 
                     FROM {$baseTable} 
                     WHERE id = ? AND geometry IS NOT NULL
                 )
                 SELECT id FROM {$intersectingTable} 
                 WHERE geometry IS NOT NULL 
                 AND EXISTS (SELECT 1 FROM base_geometry)
                 AND ST_Intersects(
                     geometry::geometry, 
                     (SELECT geometry FROM base_geometry)
                 )";

            $intersectingIds = DB::select($query, [$baseId]);
            $intersectingIds = array_column($intersectingIds, 'id');

            return $intersectingModel::whereIn('id', $intersectingIds)->get();
        } catch (\Exception $e) {
            Log::error('Error getting intersections for model', [
                'base_table' => $baseModel->getTable(),
                'base_id' => $baseModel->id,
                'intersecting_table' => $intersectingModel->getTable(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Ottiene le feature GeoJSON dei settori che intersecano una hiking route tramite bounding box.
     * Ottimizzato: usa ST_Envelope per creare un bbox veloce invece di ST_DWithin.
     * Nota: questo metodo usa un approccio approssimato basato sul bbox, più veloce ma meno preciso
     * rispetto a un'intersezione precisa sulla geometria originale.
     *
     * @param int $hikingRouteId L'ID della hiking route
     * @return array Array di feature GeoJSON per i settori intersecanti
     */
    public static function getIntersectingSectorsFeaturesByBbox(int $hikingRouteId): array
    {
        $sectorFeatures = [];

        try {
            // OTTIMIZZAZIONE: Calcola il bounding box della hiking route
            // ST_Intersects con un bbox è molto più veloce di ST_DWithin
            $bbox = DB::table('hiking_routes')
                ->where('id', $hikingRouteId)
                ->whereNotNull('geometry')
                ->selectRaw('ST_Envelope(geometry::geometry) as bbox')
                ->value('bbox');

            if (!$bbox) {
                return $sectorFeatures;
            }

            // Trova tutti i settori che intersecano il bounding box
            $intersectingSectorIds = DB::table('sectors')
                ->whereNotNull('geometry')
                ->whereRaw('ST_Intersects(geometry::geometry, ?::geometry)', [$bbox])
                ->pluck('id')
                ->toArray();

            if (!empty($intersectingSectorIds)) {
                // Carica i settori con le loro informazioni
                $sectors = Sector::whereIn('id', $intersectingSectorIds)->get();

                // Carica tutte le geometrie in una singola query ottimizzata
                $sectorsGeometries = DB::table('sectors')
                    ->whereIn('id', $intersectingSectorIds)
                    ->whereNotNull('geometry')
                    ->select('id', DB::raw('ST_AsGeoJSON(geometry) as geom'))
                    ->get()
                    ->keyBy('id');

                // Carica le percentuali dalla tabella pivot in una singola query
                // Solo per i settori che sono effettivamente associati a questa hiking route
                $sectorPercentages = DB::table('hiking_route_sector')
                    ->where('hiking_route_id', $hikingRouteId)
                    ->whereIn('sector_id', $intersectingSectorIds)
                    ->pluck('percentage', 'sector_id')
                    ->toArray();

                foreach ($sectors as $sector) {
                    $sectorGeometry = $sectorsGeometries->get($sector->id);

                    if (!$sectorGeometry || !$sectorGeometry->geom) {
                        continue;
                    }

                    try {
                        $geometry = json_decode($sectorGeometry->geom, true);

                        if ($geometry) {
                            // Recupera la percentuale dalla tabella pivot o dal pivot del modello
                            $percentage = $sector->pivot->percentage ?? $sectorPercentages[$sector->id] ?? null;

                            // Costruisci il tooltip con nome e percentuale
                            $sectorName = $sector->human_name ?? $sector->name ?? $sector->full_code ?? '';
                            $tooltip = $sectorName;
                            if ($percentage !== null) {
                                $tooltip .= ' (' . number_format($percentage, 2) . '%)';
                            }

                            $sectorFeature = [
                                'type' => 'Feature',
                                'geometry' => $geometry,
                                'properties' => [
                                    'id' => $sector->id,
                                    'name' => $sectorName,
                                    'full_code' => $sector->full_code ?? '',
                                    'percentage' => $percentage,
                                    'strokeColor' => '#FFA500', // Arancione
                                    'strokeWidth' => 2,
                                    'fillColor' => 'rgba(255, 165, 0, 0.2)', // Arancione semi-trasparente
                                    'tooltip' => $tooltip,
                                    'link' => url('/resources/sectors/' . $sector->id),
                                ],
                            ];
                            $sectorFeatures[] = $sectorFeature;
                        }
                    } catch (\Exception $e) {
                        // Log dell'errore ma continua con gli altri settori
                        Log::warning('Error adding sector to map', [
                            'hiking_route_id' => $hikingRouteId,
                            'sector_id' => $sector->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log dell'errore ma non bloccare il rendering della mappa
            Log::warning('Error loading intersecting sectors for map', [
                'hiking_route_id' => $hikingRouteId,
                'error' => $e->getMessage(),
            ]);
        }

        return $sectorFeatures;
    }
}
