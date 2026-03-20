<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use App\Models\SignageProject;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;

/**
 * Action per aggiungere HikingRoute selezionate a un SignageProject
 */
class AddHikingRoutesToSignageProject extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Add to Signage Project');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = Auth::user();

        if (! $user) {
            return Action::danger(__('User not authenticated'));
        }

        $signageProjectId = $fields->get('signage_project');
        $signageProject = SignageProject::find($signageProjectId);

        if (! $signageProject) {
            return Action::danger(__('Signage project not found'));
        }

        // Check that the user is the owner of the project
        if ($signageProject->user_id !== $user->id) {
            return Action::danger(__('You do not have permission to add routes to this project. Only the project creator or an administrator can add hiking routes.'));
        }

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($models as $hikingRoute) {
            $hikingRouteToAttach = $hikingRoute;

            if ((int) $hikingRouteToAttach->app_id !== 1) {
                $parentId = $hikingRouteToAttach->parent_hiking_route_id;

                if (! $parentId) {
                    $skippedCount++;
                    continue;
                }

                $parent = HikingRoute::find($parentId);
                if (! $parent || (int) $parent->app_id !== 1) {
                    $skippedCount++;
                    continue;
                }

                $hikingRouteToAttach = $parent;
            }

            if (! $signageProject->hikingRoutes->contains($hikingRouteToAttach->id)) {
                $signageProject->hikingRoutes()->attach($hikingRouteToAttach->id);
                $addedCount++;
            }
        }

        if ($addedCount === 0 && $skippedCount === 0) {
            return Action::message(__('All selected hiking routes are already in this signage project'));
        }

        if ($addedCount > 0 && $skippedCount === 0) {
            return Action::message(__(':count hiking routes added to signage project ":project"', [
                'count' => $addedCount,
                'project' => $signageProject->getStringName(),
            ]));
        }

        if ($addedCount === 0 && $skippedCount > 0) {
            return Action::danger(__('No hiking routes were added to signage project ":project". :skipped routes are not present in "Rete Escursionistica".', [
                'project' => $signageProject->getStringName(),
                'skipped' => $skippedCount,
            ]));
        }

        return Action::message(__(':added hiking routes added to signage project ":project". :skipped routes were not added because they are not present in "Rete Escursionistica".', [
            'project' => $signageProject->getStringName(),
            'added' => $addedCount,
            'skipped' => $skippedCount,
        ]));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [
            Select::make(__('Signage Project'), 'signage_project')
                ->options(function () {
                    $user = Auth::user();

                    if (! $user) {
                        return [];
                    }

                    $query = SignageProject::query();
                    $query->where('user_id', $user->id);

                    return $query->get()
                        ->mapWithKeys(function ($project) {
                            $name = $project->getStringName();

                            return [$project->id => $name ?: 'Project #'.$project->id];
                        })
                        ->sort()
                        ->toArray();
                })
                ->searchable()
                ->rules('required')
                ->help(function () {
                    $user = Auth::user();
                    if ($user) {
                        return __('Select the signage project to which you want to add the routes. You can see all your projects.');
                    }

                    return __('Select the signage project to which you want to add the routes. You can only see the projects you created.');
                }),
        ];
    }
}
