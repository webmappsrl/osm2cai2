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

/**
 * This job recalculates intersections between two models with spatial data trait.
 *
 * We store only the base model's ID and class, along with the intersecting model's class,
 * rather than the full model instances. This approach solves serialization issues that occur
 * when Laravel tries to queue models with complex attributes like geometries.
 */
class RecalculateIntersectionsJob implements ShouldQueue
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

        // Retrieve the base model and create a new instance of the intersecting model
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

        //check if base model has intersectings column
        if (! Schema::hasColumn($baseModel->getTable(), 'intersectings')) {
            throw new \Exception($baseModel->getTable().' does not have intersectings column. The column is required to store the intersections.');
        }

        try {
            // Calculate intersections
            $results = $baseModel->getIntersections($intersectingModel)->pluck('updated_at', 'id')->toArray();

            // Update model (should have intersectings column)
            $baseModel->update(['intersectings' => [$intersectingModel->getTable() => $results]]);
        } catch (\Exception $e) {
            Log::error('Error recalculating intersections for model '.$baseModel->getTable().': '.$e->getMessage());
            throw new \Exception('Error recalculating intersections for model '.$baseModel->getTable().': '.$e->getMessage());
        }
    }
}
