<?php

namespace App\Nova\Actions;

use App\Exports\UgcPoiFilteredModelExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Maatwebsite\Excel\Facades\Excel;
use Wm\WmPackage\Enums\ExportFormat;
use Wm\WmPackage\Exporters\ModelExporter;
use Wm\WmPackage\Nova\Actions\ExportTo;

/**
 * Custom ExportTo action for UGC POI with filtered columns
 */
class ExportUgcPoiTo extends ExportTo
{
    use InteractsWithQueue, Queueable;

    protected array $excludedKeys;

    protected array $includedPropertiesExceptions;

    protected array $propertiesColumnLabels;

    protected ?array $excludedPropertiesKeys = null;

    public function __construct(
        array $excludedKeys,
        array $includedPropertiesExceptions,
        array $propertiesColumnLabels = []
    ) {
        // Will be set after processing excluded keys
        parent::__construct(
            [],
            [],
            'ugc-poi',
            ModelExporter::DEFAULT_STYLE,
            ExportFormat::XLSX->value,
            ['properties']
        );
        $this->excludedKeys = $excludedKeys;
        $this->includedPropertiesExceptions = $includedPropertiesExceptions;
        $this->propertiesColumnLabels = $propertiesColumnLabels;
    }

    /**
     * Process excluded keys and prepare columns for export
     */
    protected function prepareColumns(): void
    {
        if (! empty($this->columns)) {
            return; // Already prepared
        }

        // Get all table columns
        $allColumns = Schema::getColumnListing('ugc_pois');

        // Separate table columns and properties keys
        $excludedColumns = array_values(array_filter($this->excludedKeys, function ($key) use ($allColumns) {
            return ! str_starts_with($key, 'properties.') && in_array($key, $allColumns);
        }));
        $this->excludedPropertiesKeys = array_values(array_filter($this->excludedKeys, function ($key) {
            return str_starts_with($key, 'properties.');
        }));

        // Filter columns excluding the specified ones
        $includedColumns = array_diff($allColumns, $excludedColumns);

        // Convert to key => value format for labels
        $this->columns = [];
        foreach ($includedColumns as $column) {
            $this->columns[$column] = ucfirst(str_replace('_', ' ', $column));
        }
    }

    protected function getExcludedPropertiesKeys(): array
    {
        $this->prepareColumns();

        return $this->excludedPropertiesKeys ?? [];
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        // Prepare columns from excluded keys
        $this->prepareColumns();

        $format = isset($fields->format) ? $fields->format : $this->defaultFormat;
        $uniqueId = now()->timestamp;
        $fileName = $this->fileName . '_' . $uniqueId . '.' . ExportFormat::from($format)->extension();

        // Create a custom ModelExporter that filters expanded columns
        $exporter = new UgcPoiFilteredModelExporter(
            $models,
            $this->columns,
            $this->relations,
            $this->styles,
            $this->getExcludedPropertiesKeys(),
            $this->includedPropertiesExceptions,
            $this->propertiesColumnLabels
        );

        // Enable JSON columns expansion (properties by default)
        if (! empty($this->expandJsonColumns)) {
            $exporter->expandJsonColumns($this->expandJsonColumns);
        }

        Excel::store(
            $exporter,
            $fileName,
            'public',
            $format,
        );

        $signedUrl = URL::temporarySignedRoute(
            'download.export',
            now()->addMinutes(5),
            ['fileName' => $fileName]
        );

        return Action::redirect($signedUrl);
    }
}
