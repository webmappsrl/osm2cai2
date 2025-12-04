<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Exporters\ModelExporter;

/**
 * Custom ModelExporter for UGC POI that filters expanded columns
 */
class UgcPoiFilteredModelExporter extends ModelExporter
{
    protected array $excludedPropertiesKeys;

    protected array $includedPropertiesExceptions;

    protected array $propertiesColumnLabels;

    protected ?array $filteredHeaders = null;

    protected ?array $headerToKeyMap = null;

    public function __construct(
        Collection $models,
        array $columns,
        array $relations,
        array $styles,
        array $excludedPropertiesKeys,
        array $includedPropertiesExceptions,
        array $propertiesColumnLabels = []
    ) {
        parent::__construct($models, $columns, $relations, $styles);
        $this->excludedPropertiesKeys = $excludedPropertiesKeys;
        $this->includedPropertiesExceptions = $includedPropertiesExceptions;
        $this->propertiesColumnLabels = $propertiesColumnLabels;
    }

    /**
     * Check if a key should be excluded
     */
    protected function shouldExcludeKey(string $key): bool
    {
        // Exceptions are never excluded
        if (in_array($key, $this->includedPropertiesExceptions)) {
            return false;
        }

        // Check if the key matches or starts with an excluded key
        foreach ($this->excludedPropertiesKeys as $excludedKey) {
            if ($key === $excludedKey || strpos($key, $excludedKey . '.') === 0) {
                return true;
            }
        }

        return false;
    }

    public function headings(): array
    {
        if ($this->filteredHeaders !== null) {
            return $this->filteredHeaders;
        }

        $headers = parent::headings();

        // Create mapping between header (label) and original keys
        $this->headerToKeyMap = [];
        if (! empty($this->columns)) {
            foreach ($this->columns as $key => $value) {
                $columnKey = is_numeric($key) ? $value : $key;
                $headerLabel = __($value);
                $this->headerToKeyMap[$headerLabel] = $columnKey;
            }
        }

        // Filter expanded properties columns that we don't want
        $filteredHeaders = array_filter($headers, function ($header) {
            return ! $this->shouldExcludeKey($header);
        });

        // Ensure included exceptions are always present in headers (even if not in data)
        foreach ($this->includedPropertiesExceptions as $exceptionKey) {
            if (! in_array($exceptionKey, $filteredHeaders)) {
                $filteredHeaders[] = $exceptionKey;
            }
        }

        // Apply custom labels to properties columns and update mapping
        $labeledHeaders = [];
        foreach ($filteredHeaders as $originalHeader) {
            // If there's a custom label for this header, use it
            // Otherwise keep the original header
            $label = $this->propertiesColumnLabels[$originalHeader] ?? $originalHeader;
            $labeledHeaders[] = $label;

            // Update headerToKeyMap: map label to original key
            if ($label !== $originalHeader) {
                $this->headerToKeyMap[$label] = $originalHeader;
            } else {
                // If no custom label, ensure mapping exists for consistency
                if (! isset($this->headerToKeyMap[$label])) {
                    $this->headerToKeyMap[$label] = $originalHeader;
                }
            }
        }

        // Save filtered headers to use them in collection()
        $this->filteredHeaders = array_values($labeledHeaders);

        return $this->filteredHeaders;
    }

    public function collection(): Collection
    {
        $collection = parent::collection();

        // Get filtered headers (calculates them if not already done)
        $filteredHeaders = $this->headings();

        // Extract coordinates from geometry for models that don't have properties.position
        $geometryCoordinates = $this->extractGeometryCoordinates();

        // Filter and reorder data based on header order
        return $collection->map(function ($row, $index) use ($filteredHeaders, $geometryCoordinates) {
            // Filter excluded columns
            foreach (array_keys($row) as $key) {
                if ($this->shouldExcludeKey($key)) {
                    unset($row[$key]);
                }
            }

            // Define coordinate keys
            $latKey = 'properties.position.latitude';
            $lonKey = 'properties.position.longitude';

            // If properties.position.latitude/longitude are missing, try to get from geometry
            $modelId = $row['id'] ?? null;
            if ($modelId && isset($geometryCoordinates[$modelId])) {
                // Check if properties.position.latitude is missing or empty
                if (empty($row[$latKey]) && isset($geometryCoordinates[$modelId]['latitude'])) {
                    $row[$latKey] = $geometryCoordinates[$modelId]['latitude'];
                }

                if (empty($row[$lonKey]) && isset($geometryCoordinates[$modelId]['longitude'])) {
                    $row[$lonKey] = $geometryCoordinates[$modelId]['longitude'];
                }
            }

            // Format latitude and longitude to 6 decimal places for consistency

            if (isset($row[$latKey]) && is_numeric($row[$latKey])) {
                $row[$latKey] = round((float) $row[$latKey], 6);
            }

            if (isset($row[$lonKey]) && is_numeric($row[$lonKey])) {
                $row[$lonKey] = round((float) $row[$lonKey], 6);
            }

            // Reorder data based on filtered headers order
            // Use mapping to convert header (label) to original keys
            $orderedRow = [];
            foreach ($filteredHeaders as $headerLabel) {
                // If header is a label, convert it to original key
                // Otherwise use header directly (for expanded properties)
                $key = $this->headerToKeyMap[$headerLabel] ?? $headerLabel;
                $orderedRow[$headerLabel] = $row[$key] ?? null;
            }

            return $orderedRow;
        });
    }

    /**
     * Extract coordinates from geometry column for models that don't have properties.position
     *
     * @return array Array keyed by model ID with latitude and longitude
     */
    protected function extractGeometryCoordinates(): array
    {
        $coordinates = [];

        // Get all model IDs
        $modelIds = $this->models->pluck('id')->filter()->toArray();

        if (empty($modelIds)) {
            return $coordinates;
        }

        // Extract coordinates from geometry using PostGIS functions
        // Convert geography to geometry first, then extract coordinates
        $results = DB::table('ugc_pois')
            ->whereIn('id', $modelIds)
            ->whereNotNull('geometry')
            ->select('id', DB::raw('ST_Y(geometry::geometry) as latitude'), DB::raw('ST_X(geometry::geometry) as longitude'))
            ->get();

        foreach ($results as $result) {
            if ($result->latitude !== null && $result->longitude !== null) {
                $coordinates[$result->id] = [
                    'latitude' => (float) $result->latitude,
                    'longitude' => (float) $result->longitude,
                ];
            }
        }

        return $coordinates;
    }
}
