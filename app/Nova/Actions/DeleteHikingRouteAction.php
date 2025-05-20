<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;

class DeleteHikingRouteAction extends DestructiveAction
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $onlyOnDetail = true;

    public $name = 'DELETE';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();
        if (! $user || $user == null) {
            return Action::danger(__('User info is not available'));
        }
        if (in_array('Administrator', $roles) || in_array('National Referent', $roles) || in_array('Regional Referent', $roles)) {
            foreach ($models as $m) {
                if ($m->deleted_on_osm) {
                    $m->regions()->sync([]);
                    $m->provinces()->sync([]);
                    $m->areas()->sync([]);
                    $m->sectors()->sync([]);
                    $m->save();
                    $m->delete();
                } else {
                    return Action::danger(__('You can not delete this Hiking Route because it is not deleted from OSM'));
                }
            }

            return Action::redirect('/resources/hiking-routes');
        } else {
            return Action::danger(__('You do not have permissions to delete this Hiking Route'));
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [];
    }
}
