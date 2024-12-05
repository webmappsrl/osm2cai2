<?php

namespace App\Traits;

use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Services\GeometryService;
use App\Traits\GeoBufferTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for spatial data utilities.
 * Includes methods for GeoJSON, Shapefile, and KML generation.
 */
trait SpatialDataTrait
{
    use GeoIntersectTrait;
    use GeoBufferTrait;
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
     * Get the geometry of the given model as GeoJSON
     *
     * @return array
     */
    public function getGeometryGeojson(): ?array
    {
        $geom = DB::select('SELECT ST_AsGeoJSON(geometry) as geom FROM '.$this->getTable().' WHERE id = '.$this->id)[0]->geom;

        return json_decode($geom, true);
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
                $this->formatFeature(['popup' => 'I am a Popup'], $geom),
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
                $this->formatFeature([], $geomRaw),
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

    /**
     * Get the geometry type of the model
     *
     * @return string
     */
    public function getGeometryType(): ?string
    {
        $type = DB::select('SELECT ST_GeometryType(geometry) as type FROM '.$this->getTable().' WHERE id = '.$this->id)[0]->type;

        return $type;
    }

    /**
     * Get the bounding box of the geometry.
     *
     * @return string
     */
    public function getBoundingBox(): string
    {
        // Ensure the model has a geometry column
        if (! $this->geometry || empty($this->geometry)) {
            throw new \Exception('Model must have a geometry column to calculate bounding box.');
        }

        //ensure the geometry is a polygon or multipolygon
        if ($this->getGeometryType() !== 'ST_Polygon' && $this->getGeometryType() !== 'ST_MultiPolygon') {
            throw new \Exception('Model must have a polygon or multipolygon geometry to calculate bounding box.');
        }

        // Get the bounding box
        $boundingBox = DB::selectOne("
        SELECT ST_AsText(ST_Envelope(geometry)) AS bbox
        FROM {$this->getTable()}
        WHERE id = ?
    ", [$this->id]);

        if ($boundingBox && $boundingBox->bbox) {
            return $boundingBox->bbox; // Returns as WKT (e.g., "POLYGON((x1 y1, x2 y2, ...))")
        }

        throw new \Exception('Failed to calculate bounding box for geometry.');
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
        $name = str_replace(' ', '_', $this->name);
        $ids = $this->getSectorIds();

        if (empty($ids)) {
            throw new \RuntimeException('No sectors found for this model');
        }

        // Create directories
        $baseDir = Storage::disk('public')->path('shape_files');
        $zipDir = $baseDir.'/zip';

        if (! file_exists($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        if (! file_exists($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        // Check if ogr2ogr is available
        exec('which ogr2ogr', $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \RuntimeException('ogr2ogr command not found. Please install GDAL.');
        }

        // Absolute paths for the files
        $shpFile = $baseDir.'/'.$name.'.shp';
        $zipFile = $zipDir.'/'.$name.'.zip';

        // Remove existing files
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        // Build and execute the ogr2ogr command
        $command = sprintf(
            'ogr2ogr -f "ESRI Shapefile" %s PG:"dbname=\'%s\' host=\'%s\' port=\'%s\' user=\'%s\' password=\'%s\'" -sql "SELECT geometry, id, name FROM sectors WHERE id IN (%s);"',
            escapeshellarg($shpFile),
            config('database.connections.pgsql.database'),
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.password'),
            implode(',', $ids)
        );

        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \RuntimeException('Error creating shapefile: '.implode("\n", $output));
        }

        // Create the zip file
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot create zip file');
        }

        // Add all related files to the shapefile
        foreach (glob($baseDir.'/'.$name.'.*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // Clean up temporary files
        foreach (glob($baseDir.'/'.$name.'.*') as $file) {
            unlink($file);
        }

        return 'shape_files/zip/'.$name.'.zip';
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
            $kml .= '<Placemark>'.$geometry.'</Placemark>';
        }

        return $kml.'</Document></kml>';
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

    /**
     * Get the area of the given model (only for polygons and multipolygons)
     *
     * @return int
     */
    public function getArea(): ?int
    {
        $model = $this;
        $table = $model->getTable();
        $id = $model->id;

        $areaQuery = 'SELECT ST_Area(geometry) as area FROM '.$table.' WHERE id = :id';
        $area = DB::select($areaQuery, ['id' => $id])[0]->area / 1000000;

        return (int) round($area);
    }

    private function fetchGeometry(string $column): ?string
    {
        //check if the column exists
        if (! Schema::hasColumn($this->getTable(), $column)) {
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

    public function getSectorIds(): array
    {
        if ($this instanceof Sector) {
            return [$this->id];
        }

        if ($this instanceof Region) {
            return $this->provinces
                ->flatMap(fn ($province) => $province->getSectorIds()) //flatten the array of arrays
                ->unique() //remove duplicates
                ->values() //reset the keys
                ->toArray();
        }

        if ($this instanceof Province) {
            return $this->areas
                ->flatMap(fn ($area) => $area->getSectorIds()) //flatten the array of arrays
                ->unique() //remove duplicates
                ->values() //reset the keys
                ->toArray();
        }

        return $this->sectors?->pluck('id')->toArray() ?? [];
    }

    private function generateShapefile(string $directory, string $sql): string
    {
        $name = str_replace(' ', '_', $this->name);
        $path = Storage::disk('public')->path($directory);

        Storage::disk('public')->makeDirectory("{$directory}/zip");
        chdir($path);

        $this->clearPreviousShapefile($name, $directory);
        exec("ogr2ogr -f 'ESRI Shapefile' {$name}.shp PG:'".$this->buildPgConnectionString()."' -sql \"{$sql}\"");
        exec("zip {$name}.zip {$name}.* && mv {$name}.zip zip/ && rm {$name}.*");

        return "{$directory}/zip/{$name}.zip";
    }

    private function buildPgConnectionString(): string
    {
        return "dbname='".config('database.connections.pgsql.database').
            "' host='".config('database.connections.pgsql.host').
            "' port='".config('database.connections.pgsql.port').
            "' user='".config('database.connections.pgsql.username').
            "' password='".config('database.connections.pgsql.password')."'";
    }

    private function clearPreviousShapefile(string $name, string $directory): void
    {
        if (Storage::disk('public')->exists("{$directory}/zip/{$name}.zip")) {
            Storage::disk('public')->delete("{$directory}/zip/{$name}.zip");
        }
    }
}
