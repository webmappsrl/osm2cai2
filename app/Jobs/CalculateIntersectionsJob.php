<?php

namespace App\Jobs;

use App\Services\GeometryService;
use App\Traits\SpatialDataTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * This job calculates intersections between two models with spatial data trait.
 *
 * We store only the base model's ID and class, along with the intersecting model's class,
 * rather than the full model instances. This approach solves serialization issues that occur
 * when Laravel tries to queue models with complex attributes like geometries.
 */
class CalculateIntersectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    protected $baseModel;

    protected $intersectingModelClass;

    /**
     * Create a new job instance.
     *
     * @param  Model  $baseModel  The model to calculate intersections from
     * @param  string  $intersectingModelClass  The class name of the model to find intersections with
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

        // If baseModel is a Nova resource, get the underlying Eloquent model
        // This is a safety check in case a Nova resource is accidentally passed
        if (is_object($baseModel) && method_exists($baseModel, 'model')) {
            $baseModel = $baseModel->model();
        }

        // Normalize the intersecting model class name
        $intersectingModelClass = $this->intersectingModelClass;
        if (str_starts_with($intersectingModelClass, 'App\\Models\\')) {
            // Already correct
        } elseif (str_starts_with($intersectingModelClass, 'App\\')) {
            // Replace wrong App\ namespace with App\Models\
            $intersectingModelClass = 'App\\Models\\' . substr($intersectingModelClass, 5);
        } else {
            // Add App\Models\ prefix
            $intersectingModelClass = 'App\\Models\\' . $intersectingModelClass;
        }
        $intersectingModelInstance = new $intersectingModelClass; // create a new instance of the intersecting model

        $baseModelClass = get_class($baseModel);
        $baseModelId = $baseModel->id;
        $baseTable = $baseModel->getTable();
        $intersectingTable = $intersectingModelInstance->getTable();

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
            // Ricarica il modello dal database per assicurarsi che abbia la geometria
            $baseModel = $baseModelClass::find($baseModelId);
            if (!$baseModel) {
                Log::error('CalculateIntersectionsJob fallito: modello base non trovato nel database', [
                    'base_model' => $baseModelClass,
                    'base_model_id' => $baseModelId,
                ]);
                throw new \Exception("Base model {$baseModelId} not found");
            }

            $intersectingIds = GeometryService::getIntersections($baseModel, $intersectingModelClass)
                ->pluck('id')
                ->toArray();

            // Check if there is a direct relationship between the two models
            $directRelations = [
                'areas' => ['provinces' => 'province_id'],
                'cai_huts' => ['regions' => 'region_id'],
                'clubs' => ['regions' => 'region_id'],
                'ec_pois' => ['regions' => 'region_id'],
                'provinces' => ['regions' => 'region_id'],
                'sectors' => ['areas' => 'area_id'],
            ];

            if (isset($directRelations[$intersectingTable][$baseTable])) {
                // Relazione diretta con foreign key
                $foreignKey = $directRelations[$intersectingTable][$baseTable];

                // update the foreign key for all intersecting records
                $updatedCount = $intersectingModelClass::whereIn('id', $intersectingIds)
                    ->update([$foreignKey => $baseModelId]);

                // set NULL for all records that no longer intersect
                $nullifiedCount = $intersectingModelClass::whereNotIn('id', $intersectingIds)
                    ->where($foreignKey, $baseModelId)
                    ->update([$foreignKey => null]);
            } else {
                // Relazione many-to-many con pivot table
                $pivotTable = $this->determinePivotTable($baseModel->getTable(), $intersectingModelInstance->getTable());

                // Sync relationships in pivot table
                $deletedCount = DB::table($pivotTable)->where([
                    $this->getModelForeignKey($baseModel) => $baseModelId,
                ])->delete();

                $pivotRecords = [];

                foreach ($intersectingIds as $intersectingId) {
                    // Use find instead of findOrFail to handle deleted records gracefully
                    $intersectingModel = $intersectingModelInstance::find($intersectingId);

                    if (!$intersectingModel) {
                        Log::warning("Modello intersecante non trovato, saltato", [
                            'base_model' => $baseModelClass,
                            'base_model_id' => $baseModelId,
                            'intersecting_model' => $intersectingModelClass,
                            'intersecting_id' => $intersectingId,
                        ]);
                        continue;
                    }

                    $baseModelForeignKey = $this->getModelForeignKey($baseModel);
                    $intersectingModelForeignKey = $this->getModelForeignKey($intersectingModel);

                    $record = [
                        $baseModelForeignKey => $baseModelId,
                        $intersectingModelForeignKey => $intersectingId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (Schema::hasColumn($pivotTable, 'percentage')) {
                        try {
                            $percentage = $this->calculateIntersectionPercentage($baseModel, $intersectingModel);
                            $record['percentage'] = $percentage;
                        } catch (\Exception $e) {
                            Log::warning("Errore nel calcolo della percentuale di intersezione, uso 0", [
                                'base_model' => $baseModelClass,
                                'base_model_id' => $baseModelId,
                                'intersecting_model' => $intersectingModelClass,
                                'intersecting_id' => $intersectingId,
                                'error' => $e->getMessage(),
                            ]);
                            $record['percentage'] = 0.0;
                        }
                    }

                    $pivotRecords[] = $record;
                }

                if (!empty($pivotRecords)) {
                    try {
                        DB::table($pivotTable)->insert($pivotRecords);
                    } catch (\Exception $e) {
                        Log::error('Errore durante l\'inserimento dei record pivot', [
                            'base_model' => $baseModelClass,
                            'base_model_id' => $baseModelId,
                            'intersecting_model' => $intersectingModelClass,
                            'pivot_table' => $pivotTable,
                            'records_count' => count($pivotRecords),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Errore durante il calcolo delle intersezioni', [
                'base_model' => $baseModelClass,
                'base_model_id' => $baseModelId ?? null,
                'base_model_table' => $baseTable ?? null,
                'intersecting_model' => $intersectingModelClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Determine pivot table name based on models
     */
    private function determinePivotTable(string $baseModelTable, string $intersectingModelTable): string
    {
        // Definizione delle relazioni dirette (con foreign key)
        $directRelations = [
            'areas' => ['provinces' => 'province_id'],
            'cai_huts' => ['regions' => 'region_id'],
            'clubs' => ['regions' => 'region_id'],
            'ec_pois' => ['regions' => 'region_id'],
        ];

        // Verifica se esiste una relazione diretta
        if (isset($directRelations[$intersectingModelTable][$baseModelTable])) {
            return $directRelations[$intersectingModelTable][$baseModelTable];
        }

        // Tabelle pivot esistenti
        $tables = [
            'hiking_routes' => [
                'regions' => 'hiking_route_region',
                'provinces' => 'hiking_route_province',
                'sectors' => 'hiking_route_sector',
                'areas' => 'area_hiking_route',
                'mountain_groups' => 'mountain_group_hiking_route',
                'ec_pois' => 'hiking_route_ec_poi',
                'clubs' => 'hiking_route_club',
                'cai_huts' => 'hiking_route_cai_hut',
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
                'ec_pois' => 'cai_hut_ec_poi',
            ],
            'ec_pois' => [
                'mountain_groups' => 'mountain_group_ec_poi',
                'clubs' => 'ec_poi_club',
                'cai_huts' => 'ec_poi_cai_hut',
                'hiking_routes' => 'hiking_route_ec_poi',
            ],
            'clubs' => [
                'mountain_groups' => 'mountain_group_club',
                'ec_pois' => 'ec_poi_club',
            ],

        ];

        if (isset($tables[$baseModelTable][$intersectingModelTable])) {
            return $tables[$baseModelTable][$intersectingModelTable];
        }
        if (isset($tables[$intersectingModelTable][$baseModelTable])) {
            return $tables[$intersectingModelTable][$baseModelTable];
        }

        throw new \Exception("No relationship found for {$baseModelTable} and {$intersectingModelTable}");
    }

    /**
     * Get foreign key name for a model
     */
    private function getModelForeignKey($model): string
    {
        if (is_string($model)) {
            $model = new $model;
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
            $query = DB::select("
                SELECT (ST_Length(
                    ST_Intersection(
                        (SELECT geometry FROM {$baseModel->getTable()} WHERE id = ?),
                        (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = ?)
                    ), true
                ) / ST_Length(
                    (SELECT geometry FROM {$baseModel->getTable()} WHERE id = ?), 
                    true
                )) * 100 as percentage
            ", [$baseModel->id, $intersectingModel->id, $baseModel->id]);
        } elseif ($intersectingModel->getTable() === 'hiking_routes') {
            $query = DB::select("
                SELECT (ST_Length(
                    ST_Intersection(
                        (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = ?),
                        (SELECT geometry FROM {$baseModel->getTable()} WHERE id = ?)
                    ), true
                ) / ST_Length(
                    (SELECT geometry FROM {$intersectingModel->getTable()} WHERE id = ?), 
                    true
                )) * 100 as percentage
            ", [$intersectingModel->id, $baseModel->id, $intersectingModel->id]);
        } else {
            // case for multipolygons
            $query = DB::select('
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
            ', [
                $baseModel->geometry,
                $intersectingModel->geometry,
                $baseModel->geometry,
                $baseModel->geometry,
            ]);
        }

        $percentage = (float) ($query[0]->percentage ?? 0.0);

        // Log per debug
        Log::info('Percentuale di intersezione calcolata', [
            'base_model' => get_class($baseModel),
            'base_model_id' => $baseModel->id,
            'base_table' => $baseModel->getTable(),
            'intersecting_model' => get_class($intersectingModel),
            'intersecting_id' => $intersectingModel->id,
            'intersecting_table' => $intersectingModel->getTable(),
            'percentage' => $percentage,
        ]);

        return $percentage;
    }
}
