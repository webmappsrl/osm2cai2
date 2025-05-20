<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KmlController extends Controller
{
    /**
     * Allowed models for KML generation.
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
     * @param  string  $modelType  The type of the model
     * @param  int  $id  The ID of the model
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, string $modelType, int $id)
    {
        $modelType = Str::lower($modelType);

        // Validate model type
        if (! isset($this->allowedModels[$modelType])) {
            return response()->json(['error' => 'Invalid model type'], 404);
        }

        $modelClass = $this->allowedModels[$modelType];
        $model = $modelClass::find($id);

        if (! $model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        try {
            $kmlData = $model->getKml();

            return response($kmlData)
                ->header('Content-Type', 'application/vnd.google-earth.kml+xml')
                ->header('Content-Disposition', 'attachment; filename="'.$model->getTable().'_'.date('Ymd').'.kml"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
