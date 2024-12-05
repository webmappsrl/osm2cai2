<?php

namespace App\Jobs;

use DB;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Traits\SpatialDataTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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


    protected $baseModel;

    protected $intersectingModelClass;

    /**
     * Create a new job instance.
     *
     * @param Model $baseModel The model to calculate intersections from
     * @param string $intersectingModelClass The class name of the model to find intersections with
     */
    public function __construct($baseModel, $intersectingModelClass)
    {
        $this->baseModel = $baseModel;
        $this->intersectingModelClass = $intersectingModelClass;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $baseModel = $this->baseModel;
        $intersectingModelInstance = new $this->intersectingModelClass(); //create a new instance of the intersecting model

        // Check if models are different
        if (get_class($baseModel) === $this->intersectingModelClass) {
            throw new \Exception('Models must be different');
        }

        // Ensure base model has SpatialDataTrait
        if (! in_array(SpatialDataTrait::class, class_uses_recursive($baseModel))) {
            throw new \Exception('Base model must have SpatialDataTrait for intersections');
        }
        // Ensure intersecting model has SpatialDataTrait
        if (! in_array(SpatialDataTrait::class, class_uses_recursive($intersectingModelInstance))) {
            throw new \Exception('Intersecting model must have SpatialDataTrait for intersections');
        }

        try {
            // Calculate intersections
            $intersectingIds = $baseModel->getIntersections($intersectingModelInstance)
                ->pluck('id')
                ->toArray();

            // Determine pivot table name based on models
            $pivotTable = $this->determinePivotTable($baseModel->getTable(), $intersectingModelInstance->getTable());

            // Sync relationships in pivot table
            DB::table($pivotTable)->where([
                $this->getModelForeignKey($baseModel) => $baseModel->id
            ])->delete();

            $pivotRecords = array_map(function ($intersectingId) use ($baseModel, $intersectingModelInstance, $pivotTable) {
                $intersectingModel = $intersectingModelInstance::findOrFail($intersectingId);
                $baseModelForeignKey = $this->getModelForeignKey($baseModel);
                $intersectingModelForeignKey = $this->getModelForeignKey($intersectingModel);

                $hasPercentageColumn = Schema::hasColumn($pivotTable, 'percentage');
                if ($hasPercentageColumn) {
                    $percentage = $this->calculateIntersectionPercentage($baseModel, $intersectingModel, $pivotTable);
                    Log::info("Calculated intersection percentage for {$baseModel->getTable()} ID {$baseModel->id} and {$intersectingModelInstance->getTable()} ID {$intersectingId}: {$percentage}%");
                }

                $record = [
                    $baseModelForeignKey => $baseModel->id,
                    $intersectingModelForeignKey => $intersectingId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasPercentageColumn) {
                    $record['percentage'] = $percentage;
                }

                return $record;
            }, $intersectingIds);


            \DB::table($pivotTable)->insert($pivotRecords);
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
                'mountain_groups' => 'mountain_group_hiking_route',
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
                'cai_huts' => 'mountain_group_cai_hut',
                'ec_pois' => 'mountain_group_ec_poi',
                'clubs' => 'mountain_group_club',
                'hiking_routes' => 'mountain_group_hiking_route',
            ],
            'cai_huts' => [
                'mountain_groups' => 'mountain_group_cai_hut',
            ],
            'ec_pois' => [
                'mountain_groups' => 'mountain_group_ec_poi',
            ],
            'clubs' => [
                'mountain_groups' => 'mountain_group_club',
            ],

        ];

        if (isset($tables[$baseModelTable][$intersectingModelTable])) {
            return $tables[$baseModelTable][$intersectingModelTable];
        }
        if (isset($tables[$intersectingModelTable][$baseModelTable])) {
            return $tables[$intersectingModelTable][$baseModelTable];
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
    private function calculateIntersectionPercentage($baseModel, $intersectingModel): float
    {
        if (! $intersectingModel) {
            throw new \Exception('Intersecting model not found');
        }

        // special case for hiking_routes (MultiLineString): needs to calculate intersections with ST_Length
        if ($baseModel->getTable() === 'hiking_routes') {
            $query = <<<'SQL'
                SELECT (ST_Length(
                    ST_Intersection(
                        (SELECT geometry FROM {$baseModel->getTable()} WHERE id = {$baseModel->id}),
                        (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = {$intersectingModel->id})
                    ), true
                ) / ST_Length(
                    (SELECT geometry FROM {$baseModel->getTable()} WHERE id = {$baseModel->id}), 
                    true
                )) * 100 as percentage
            SQL;
        } elseif ($intersectingModel->getTable() === 'hiking_routes') {
            $query = <<<'SQL'
                SELECT (ST_Length(
                    ST_Intersection(
                        (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = {$intersectingModel->id}),
                        (SELECT geometry FROM {$baseModel->getTable()} WHERE id = {$baseModel->id})
                    ), true
                ) / ST_Length(
                    (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = {$intersectingModel->id}), 
                    true
                )) * 100 as percentage
            SQL;
        } else {
            // case for multipolygons
            return DB::select(<<<'SQL'
                WITH intersection AS (
                    SELECT ST_Intersection(
                        ST_Transform(?, 3857),
                        ST_Transform(?, 3857)
                    ) as geom
                )
                SELECT CASE 
                    WHEN ST_Area(ST_Transform(?, 3857)) > 0 
                    THEN (ST_Area((SELECT geom FROM intersection)) / ST_Area(ST_Transform(?, 3857))) * 100
                    ELSE 0 
                END as percentage
            SQL, [
                $baseModel->geometry,
                $intersectingModel->geometry,
                $baseModel->geometry,
                $baseModel->geometry
            ])[0]->percentage ?? 0.0;
        }

        $percentage = DB::select($query);

        // Log for debug
        Log::info("Calculated percentage:", [
            'base_model' => $baseModel->getTable(),
            'intersecting_model' => $intersectingModel->getTable(),
            'percentage' => $percentage[0]->percentage
        ]);

        return (float) ($percentage[0]->percentage ?? 0.0);
    }
}
