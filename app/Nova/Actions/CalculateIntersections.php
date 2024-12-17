<?php

namespace App\Nova\Actions;

use App\Models\Club;
use App\Models\Region;
use Illuminate\Bus\Queueable;
use App\Models\MountainGroups;
use App\Jobs\CheckNearbyHutsJob;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use App\Jobs\CalculateIntersectionsJob;
use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Models\CaiHut;
use App\Models\HikingRoute;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
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
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            switch ($this->class) {
                case 'EcPoi':
                    CalculateIntersectionsJob::dispatch($model, Club::class);
                    CalculateIntersectionsJob::dispatch($model, MountainGroups::class);
                    CheckNearbyHikingRoutesJob::dispatch($model, 250);
                    CheckNearbyHutsJob::dispatch($model, 250);
                    break;
                case 'MountainGroups':
                    CalculateIntersectionsJob::dispatch($model, Club::class);
                    CalculateIntersectionsJob::dispatch($model, Region::class);
                    CalculateIntersectionsJob::dispatch($model, CaiHut::class);
                    CalculateIntersectionsJob::dispatch($model, HikingRoute::class);
                    CalculateIntersectionsJob::dispatch($model, EcPoi::class);
                    break;
            }
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
