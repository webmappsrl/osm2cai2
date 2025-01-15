<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DownloadGeojsonZip extends Action
{
    use Queueable;

    public $name = 'Download Geojson';

    public function handle(ActionFields $fields, Collection $models)
    {
        $zip = new ZipArchive;
        $zipFileName = 'OSM2CAI_ugctracks_' . now()->format('Ymd') . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($models as $model) {
                $geojson = $model->getGeojson();
                $filePath = 'ugctracks/' . $model->id . '.geojson';
                Storage::disk('public')->put($filePath, json_encode($geojson));
                $zip->addFile(storage_path('app/public/' . $filePath), $model->id . '.geojson');
            }
            $zip->close();

            // Pulizia dei file temporanei
            foreach ($models as $model) {
                Storage::disk('public')->delete('ugctracks/' . $model->id . '.geojson');
            }

            return Action::download(Storage::disk('public')->url($zipFileName), $zipFileName);
        } else {
            return Action::danger(__('Error while creating zip file.'));
        }
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
