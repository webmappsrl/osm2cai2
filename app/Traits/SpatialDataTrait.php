<?php

namespace App\Traits;

use App\Services\GeometryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for spatial data utilities. 
 * Includes methods for GeoJSON, Shapefile, and KML generation.
 */
trait SpatialDataTrait
{
    // ------------------------------
    // GeoJSON Utilities
    // ------------------------------

    /**
     * Retrieve the GeoJSON representation of the model's geometry with no additional properties.
     *
     * @return array|null
     */
    public function getEmptyGeojson(): ?array
    {
        $geom = $this->fetchGeometry('geometry');
        return $geom ? $this->formatFeature([], $geom) : null;
    }

    /**
     * Get a feature collection with the model's geometry.
     *
     * @return array|null
     */
    public function getFeatureCollection(): ?array
    {
        $geom = $this->fetchGeometry('geometry');
        return $geom
            ? $this->formatFeatureCollection([
                $this->formatFeature(['popup' => 'I am a Popup'], $geom)
            ])
            : null;
    }

    /**
     * Get a GeoJSON view for the map, optionally including raw geometry.
     *
     * @return array|null
     */
    public function getGeojsonForMapView(): ?array
    {
        $geom = $this->fetchGeometry('geometry');
        $geomRaw = $this->fetchGeometry('geometry_raw_data');

        if ($geom && $geomRaw) {
            return $this->formatFeatureCollection([
                $this->formatFeature([], $geom),
                $this->formatFeature([], $geomRaw)
            ]);
        }

        return $geom ? $this->formatFeature([], $geom) : null;
    }

    /**
     * Get the centroid of the model's geometry as GeoJSON.
     *
     * @return array|null
     */
    public function getCentroidGeojson(): ?array
    {
        $centroid = $this->getCentroid();
        return $centroid ? $this->formatFeature([], json_encode($centroid)) : null;
    }

    /**
     * Get the centroid coordinates of the model's geometry.
     *
     * @return array|null
     */
    public function getCentroid(): ?array
    {
        $geom = $this->fetchGeometry('geometry');
        return json_decode(GeometryService::getService()->getCentroid($geom), true)['coordinates'] ?? null;
    }

    // ------------------------------
    // Related Data Utilities
    // ------------------------------

    /**
     * Retrieve GeoJSON for related UGC features within proximity and time constraints.
     *
     * @return array
     */
    public function getRelatedUgcGeojson(): array
    {
        $classes = [
            'App\Models\UgcPoi' => 'ugc_pois',
            'App\Models\UgcTrack' => 'ugc_tracks',
            'App\Models\UgcMedia' => 'ugc_media',
        ];

        $features = [];
        foreach ($classes as $class => $table) {
            $features = array_merge($features, $this->fetchRelatedFeatures($class, $table));
        }

        return $this->formatFeatureCollection($features);
    }

    // ------------------------------
    // Shapefile and KML Utilities
    // ------------------------------

    /**
     * Generate a shapefile for the model's sectors.
     *
     * @return string the shapefile relative URL
     */
    public function getShapefile(): string
    {
        $sectorIds = $this->getSectorIds();

        // Se non ci sono settori, ritorna un messaggio o una risposta appropriata
        if (empty($sectorIds)) {
            return "No sectors found.";
        }

        // Procedi con la generazione dello shapefile solo se ci sono id di settori
        return $this->generateShapefile(
            'shape_files',
            "SELECT ST_AsText(ST_Transform(geometry, 4326)) as geometry, id, name 
        FROM sectors WHERE id IN (" . implode(',', $sectorIds) . ")"
        );
    }



    /**
     * Generate a shapefile for hiking routes in the model's region.
     *
     * @return string the shapefile relative URL
     */
    public function getHikingRoutesShapefile(): string
    {
        return $this->generateShapefile(
            'shape_files',
            "SELECT * FROM hiking_routes AS h INNER JOIN hiking_route_region AS r ON h.id=r.hiking_route_id
             WHERE region_id={$this->id} AND osm2cai_status > 0 LIMIT 10000"
        );
    }

    /**
     * Generate a KML file for the model's sectors.
     *
     * @return string the KML data
     */
    public function getKml(): string
    {
        $kml = '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>';

        foreach ($this->getSectorIds() as $sectorId) {
            $geometry = DB::table('sectors')
                ->where('id', $sectorId)
                ->select(DB::raw('ST_AsKML(geometry) as kml'))
                ->value('kml');
            $kml .= '<Placemark>' . $geometry . '</Placemark>';
        }

        return $kml . '</Document></kml>';
    }

    // ------------------------------
    // Conversion Utilities
    // ------------------------------

    public function textToGeojson(string $text): ?array
    {
        return GeometryService::getService()->textToGeojson($text);
    }

    public function geojsonToGeometry(array $geojson): ?string
    {
        return GeometryService::getService()->geojsonToGeometry($geojson);
    }

    public function fileToGeometry(string $fileContent): ?string
    {
        $geojson = $this->textToGeojson($fileContent);
        return $geojson ? $this->geojsonToGeometry($geojson) : null;
    }

    // ------------------------------
    // Helper Methods
    // ------------------------------

    private function fetchGeometry(string $column): ?string
    {
        //check if the column exists
        if (!Schema::hasColumn($this->getTable(), $column)) {
            return null;
        }

        return optional(
            DB::table($this->getTable())
                ->where('id', $this->id)
                ->select(DB::raw("ST_AsGeoJSON({$column}) as geom"))
                ->first()
        )->geom;
    }

    private function formatFeature(array $properties, string $geometry): array
    {
        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => json_decode($geometry, true),
        ];
    }

    private function formatFeatureCollection(array $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    private function fetchRelatedFeatures(string $class, string $table): array
    {
        $features = [];
        $result = DB::select(
            "SELECT id FROM {$table}
             WHERE user_id = ? AND ABS(EXTRACT(EPOCH FROM created_at) - EXTRACT(EPOCH FROM TIMESTAMP '{$this->created_at}')) < 5400
             AND ST_DWithin(geometry, ?, 400);",
            [$this->user_id, $this->geometry]
        );

        foreach ($result as $row) {
            $geojson = $class::find($row->id)->getGeojson();
            if ($geojson) {
                $features[] = $geojson;
            }
        }

        return $features;
    }

    private function getSectorIds(): array
    {
        return $this->sectors ? $this->sectors->pluck('id')->toArray() : [];
    }

    private function generateShapefile(string $directory, string $sql): string
    {
        $name = str_replace(' ', '_', $this->name);
        $path = Storage::disk('public')->path($directory);

        Storage::disk('public')->makeDirectory("{$directory}/zip");
        chdir($path);

        $this->clearPreviousShapefile($name, $directory);
        exec("ogr2ogr -f 'ESRI Shapefile' {$name}.shp PG:'" . $this->buildPgConnectionString() . "' -sql \"{$sql}\"");
        exec("zip {$name}.zip {$name}.* && mv {$name}.zip zip/ && rm {$name}.*");

        return "{$directory}/zip/{$name}.zip";
    }

    private function buildPgConnectionString(): string
    {
        return "dbname='" . config('database.connections.pgsql.database') .
            "' host='" . config('database.connections.pgsql.host') .
            "' port='" . config('database.connections.pgsql.port') .
            "' user='" . config('database.connections.pgsql.username') .
            "' password='" . config('database.connections.pgsql.password') . "'";
    }

    private function clearPreviousShapefile(string $name, string $directory): void
    {
        if (Storage::disk('public')->exists("{$directory}/zip/{$name}.zip")) {
            Storage::disk('public')->delete("{$directory}/zip/{$name}.zip");
        }
    }
}
