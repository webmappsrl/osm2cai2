<?php

namespace App\Nova\Actions;

use ZipArchive;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadGeojsonZip extends Action
{
    use Queueable;

    public $name = 'Download Geojson ZIP';

    public function handle(ActionFields $fields, Collection $models)
    {
        $zip = new ZipArchive;
        $zipFileName = 'OSM2CAI_ugctracks_' . now()->format('Ymd') . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
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
            return Action::danger('Impossibile creare il file zip.');
        }
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
