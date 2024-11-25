<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShapeFileController extends Controller
{
    /**
     * Allowed models for Shapefile generation.
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
     * Generate and download a Shapefile for a specific model.
     *
     * @param Request $request
     * @param string $modelType The type of the model
     * @param int $id The ID of the model
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, string $modelType, int $id)
    {
        $modelType = Str::lower($modelType);

        // Validate model type
        if (! isset($this->allowedModels[$modelType])) {
            abort(404, 'Invalid model type');
        }

        $modelClass = $this->allowedModels[$modelType];
        $model = $modelClass::find($id);
        $fileName = Str::lower($model->getTable()) . '_' . ($model->name ?? $model->id) . '_' . date('Ymd');

        if (! $model) {
            abort(404, 'Model not found');
        }

        $shapefile = $model->getShapefile();

        return Storage::disk('public')->download($shapefile, $fileName . '.zip');
    }
}
