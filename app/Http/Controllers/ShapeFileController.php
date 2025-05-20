<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShapeFileController extends Controller
{
    /**
     * Allowed models for Shapefile generation.
     */
    private array $allowedModels = [
        'region' => \App\Models\Region::class,
        'province' => \App\Models\Province::class,
        'area' => \App\Models\Area::class,
        'sector' => \App\Models\Sector::class,
    ];

    /**
     * Generate and download a Shapefile for a specific model.
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
            $shapefile = $model->getShapefile();
            $fileName = Str::slug(($model->name ?? $model->id).'_'.date('Ymd')).'.zip';

            return Storage::disk('public')->download($shapefile, $fileName);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
