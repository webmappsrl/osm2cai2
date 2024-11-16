<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CsvController extends Controller
{
    /**
     * Allowed model types for CSV generation.
     *
     * @var array
     */
    private array $allowedModels = [
        'region' => \App\Models\Region::class,
        'sector' => \App\Models\Sector::class,
        'area' => \App\Models\Area::class,
        'province' => \App\Models\Province::class,
        'section' => \App\Models\Section::class,
        'users' => \App\Models\User::class,
    ];

    /**
     * Generate and download a CSV file for a specific model.
     *
     * @param Request $request
     * @param string $modelType The type of the model
     * @param int $id The ID of the model
     * @return \Illuminate\Http\Response
     */
    public function download(string $modelType, int $id)
    {
        $modelType = Str::lower($modelType);

        // Validate model type
        if (! isset($this->allowedModels[$modelType])) {
            abort(404, 'Invalid model type');
        }

        $modelClass = $this->allowedModels[$modelType];
        $model = $modelClass::find($id);

        if (! $model) {
            abort(404, 'Model not found');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="osm2cai_'.date('Ymd').'_'.$model->getTable().'_'.($model->name ?? $model->id).'.csv"',
        ];

        return response($model->getCsv(), 200, $headers);
    }
}
