<?php

namespace App\Nova\Actions;

use App\Exports\UgcPoisExport;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Maatwebsite\Excel\Facades\Excel;

class DownloadUgcCsv extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Download CSV';

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
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $resourceName = class_basename($this->resourceClass);
        $fileName = strtolower($resourceName).'-export-'.now()->format('Y-m-d').'.csv';

        $exportFields = $this->resourceClass::getExportFields();

        Excel::store(new UgcPoisExport($models, $exportFields), $fileName, 'public');

        $url = Storage::disk('public')->url($fileName);

        return Action::download($url, $fileName);
    }
}
