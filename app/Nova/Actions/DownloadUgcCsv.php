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
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadUgcCsv extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = "Download CSV";

    public $showOnIndex = true;
    public $withoutConfirmation = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $fileName = 'ugcPois-' . now()->format('Y-m-d') . '.csv';

        Excel::store(new UgcPoisExport($models), $fileName, 'public');

        $url = Storage::disk('public')->url($fileName);

        return Action::download($url, $fileName);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
