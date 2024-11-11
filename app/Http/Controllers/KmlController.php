<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KmlController extends Controller
{
    /**
     * Allowed models for KML generation.
     *
     * @var array
     */
    private array $allowedModels = [
        'region' => \App\Models\Region::class,
        'province' => \App\Models\Province::class,
        'area' => \App\Models\Area::class,
        'sector' => \App\Models\Sector::class,
    ];

    /**
     * Generate and download a KML file for a specific model.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $modelType The type of the model
     * @param int $id The ID of the model
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, string $modelType, int $id)
    {
        $modelType = Str::lower($modelType);

        // Validate model type
        if (!isset($this->allowedModels[$modelType])) {
            abort(404, 'Invalid model type');
        }

        $modelClass = $this->allowedModels[$modelType];
        $model = $modelClass::find($id);

        if (!$model) {
            abort(404, 'Model not found');
        }

        $kmlData = $model->getKml(); // Assume model has this method

        $headers = [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="' . $model->getTable() . '_' . date('Ymd') . '.kml"',
        ];

        return response($kmlData, 200, $headers);
    }
}
