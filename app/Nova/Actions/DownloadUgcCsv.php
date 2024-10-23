<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use App\Exports\UgcPoisExport;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;

class DownloadUgcCsv extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = "Download CSV";
    public $showOnIndex = true;
    public $withoutConfirmation = true;
    public $resourceClass;

    public function __construct($resourceClass)
    {
        $this->resourceClass = $resourceClass;
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $resourceName = class_basename($this->resourceClass);
        $fileName = strtolower($resourceName) . '-export-' . now()->format('Y-m-d') . '.csv';

        $exportFields = $this->resourceClass::getExportFields();

        Excel::store(new UgcPoisExport($models, $exportFields), $fileName, 'public');

        $url = Storage::disk('public')->url($fileName);

        return Action::download($url, $fileName);
    }
}
