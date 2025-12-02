<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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

        // Filter and reorder data based on header order
        return $collection->map(function ($row) use ($filteredHeaders) {
            // Filter excluded columns
            foreach (array_keys($row) as $key) {
                if ($this->shouldExcludeKey($key)) {
                    unset($row[$key]);
                }
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
}
