<?php

namespace App\Jobs;

use App\Traits\SpatialDataTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * This job recalculates intersections between two models with spatial data trait.
 *
 * We store only the base model's ID and class, along with the intersecting model's class,
 * rather than the full model instances. This approach solves serialization issues that occur
 * when Laravel tries to queue models with complex attributes like geometries.
 */
class CalculateIntersectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $baseModelClass;

    protected $baseModelId;

    protected $intersectingModelClass;

    /**
     * Create a new job instance.
     *
     * @param Model $baseModel The model to calculate intersections from
     * @param string $intersectingModelClass The class name of the model to find intersections with
     */
    public function __construct($baseModel, $intersectingModelClass)
    {
        $this->baseModelClass = get_class($baseModel);
        $this->baseModelId = $baseModel->id;
        $this->intersectingModelClass = $intersectingModelClass;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $baseModelClass = $this->baseModelClass;
        $intersectingModelClass = $this->intersectingModelClass;

        $baseModel = $baseModelClass::findOrFail($this->baseModelId);
        $intersectingModel = new $intersectingModelClass();

        // Check if models are different
        if ($baseModelClass === $intersectingModelClass) {
            throw new \Exception('Models must be different');
        }

        // Ensure base model has SpatialDataTrait
        if (! in_array(SpatialDataTrait::class, class_uses_recursive($baseModel))) {
            throw new \Exception('Base model must have SpatialDataTrait for intersections');
        }
        // Ensure intersecting model has SpatialDataTrait
        if (! in_array(SpatialDataTrait::class, class_uses_recursive($intersectingModel))) {
            throw new \Exception('Intersecting model must have SpatialDataTrait for intersections');
        }

        try {
            // Calculate intersections
            $intersectingIds = $baseModel->getIntersections($intersectingModel)
                ->pluck('id')
                ->toArray();

            // Determine pivot table name based on models
            $pivotTable = $this->determinePivotTable($baseModel->getTable(), $intersectingModel->getTable());

            // Sync relationships in pivot table
            \DB::table($pivotTable)->where([
                $this->getModelForeignKey($baseModel) => $baseModel->id
            ])->delete();

            $pivotRecords = array_map(function ($intersectingId) use ($baseModel) {
                return [
                    $this->getModelForeignKey($baseModel) => $baseModel->id,
                    $this->getModelForeignKey($this->intersectingModelClass) => $intersectingId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $intersectingIds);

            \DB::table($pivotTable)->insert($pivotRecords);

            // Calculate and update percentages
            $this->updateIntersectionPercentages($baseModel, $intersectingModel, $pivotTable);
        } catch (\Exception $e) {
            Log::error('Error recalculating intersections for model ' . $baseModel->getTable() . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Determine pivot table name based on models
     */
    private function determinePivotTable(string $baseModelTable, string $intersectingModelTable): string
    {
        $tables = [
            'hiking_routes' => [
                'regions' => 'hiking_route_region',
                'provinces' => 'hiking_route_province',
                'sectors' => 'hiking_route_sector',
                'areas' => 'area_hiking_route',
            ],
            'regions' => [
                'hiking_routes' => 'hiking_route_region',
                'mountain_groups' => 'mountain_group_region',
            ],
            'provinces' => [
                'hiking_routes' => 'hiking_route_province',
            ],
            'sectors' => [
                'hiking_routes' => 'hiking_route_sector',
            ],
            'areas' => [
                'hiking_routes' => 'area_hiking_route',
            ],
            'mountain_groups' => [
                'regions' => 'mountain_group_region',
            ],
        ];

        if (isset($tables[$baseModelTable][$intersectingModelTable])) {
            return $tables[$baseModelTable][$intersectingModelTable];
        }
        if (isset($tables[$intersectingTable][$baseTable])) {
            return $tables[$intersectingTable][$baseTable];
        }

        throw new \Exception("No pivot table found for {$baseTable} and {$intersectingTable}");
    }

    /**
     * Get foreign key name for a model
     */
    private function getModelForeignKey($model): string
    {
        if (is_string($model)) {
            $model = new $model();
        }
        return Str::singular($model->getTable()) . '_id';
    }

    /**
     * Calculate intersection percentage
     */
    private function calculateIntersectionPercentage($baseModelId, $intersectingModelClass, $intersectingId): float
    {
        return 0;
    }
}
