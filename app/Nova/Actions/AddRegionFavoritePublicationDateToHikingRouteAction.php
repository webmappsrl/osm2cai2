<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;

class AddRegionFavoritePublicationDateToHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->name = __('LOSCARPONE PUBLICATION DATE');
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
            $model->region_favorite_publication_date = $fields['publication_date'];
            $model->save();
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [
            Date::make(__('Publication Date'), 'publication_date'),
        ];
    }
}
