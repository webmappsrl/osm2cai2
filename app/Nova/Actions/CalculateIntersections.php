<?php

namespace App\Nova\Actions;

use App\Jobs\CalculateIntersectionsJob;
use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Jobs\CheckNearbyHutsJob;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class CalculateIntersections extends Action
{
    use InteractsWithQueue, Queueable;

    protected $class;

    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * Perform the action on the given models.
     *
     * @param  ActionFields  $fields
     * @param  Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            switch ($this->class) {
                case 'EcPoi':
                    CalculateIntersectionsJob::dispatch($model, Club::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, MountainGroups::class)->onQueue('geometric-computations');
                    CheckNearbyHikingRoutesJob::dispatch($model, 250)->onQueue('geometric-computations');
                    CheckNearbyHutsJob::dispatch($model, 250)->onQueue('geometric-computations');
                    break;
                case 'MountainGroups':
                    CalculateIntersectionsJob::dispatch($model, Club::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, Region::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, CaiHut::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, HikingRoute::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, EcPoi::class)->onQueue('geometric-computations');
                    break;
                case 'Region':
                    CalculateIntersectionsJob::dispatch($model, MountainGroups::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, CaiHut::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, HikingRoute::class)->onQueue('geometric-computations');
                    CalculateIntersectionsJob::dispatch($model, EcPoi::class)->onQueue('geometric-computations');
                    break;
            }
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
