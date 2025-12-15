<?php

namespace App\Nova\Actions;

use App\Exports\HikingRouteSignageExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Maatwebsite\Excel\Facades\Excel;
use Wm\WmPackage\Enums\ExportFormat;
use Wm\WmPackage\Exporters\ModelExporter;
use Wm\WmPackage\Nova\Actions\ExportTo;

/**
 * Action per esportare HikingRoute con i Poles associati per la segnaletica
 */
class ExportHikingRouteSignageTo extends ExportTo
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        parent::__construct(
            [],
            [],
            'hiking-route-segnaletica',
            ModelExporter::DEFAULT_STYLE,
            ExportFormat::XLSX->value,
            ['properties']
        );
    }

    public function name()
    {
        return __('Esporta Segnaletica');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $format = isset($fields->format) ? $fields->format : $this->defaultFormat;
        $uniqueId = now()->timestamp;
        $fileName = $this->fileName . '_' . $uniqueId . '.' . ExportFormat::from($format)->extension();

        $exporter = new HikingRouteSignageExporter(
            $models,
            $this->columns,
            $this->styles
        );

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

