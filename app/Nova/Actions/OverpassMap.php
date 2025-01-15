<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;

class OverpassMap extends Action
{
    use InteractsWithQueue, Queueable;

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = HikingRoute::find($resourceId);
        }

        $this->name = __('SEARCH POINTS OF INTEREST');
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
        $overpassQuery = urlencode($fields->overpass_query);

        return Action::openInNewTab('https://overpass-turbo.eu/map.html?Q='.$overpassQuery);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        $relationId = $this->model->relation_id;
        if (! empty(auth()->user()->default_overpass_query)) {
            $query = auth()->user()->default_overpass_query;
            //search for all the occurrencies of '@osm_id' and replace them with the relation id
            $query = str_replace('@osm_id', $relationId, $query);
        } else {
            $query = '[out:xml]
[timeout:250];
(
(
(
rel('.$relationId.');node(around:1000)["amenity"~"monastery|place_of_worship|ruins"];
rel('.$relationId.');node(around:1000)["historic"~"castle|archaeological_site|tower|city_gate|ruins"];
rel('.$relationId.');node(around:1000)["building"~"castle|monastery|ruins|tower"];
rel('.$relationId.');node(around:1000)["religion"="christian"];
rel('.$relationId.');node(around:1000)["man_made"="tower"];
);
rel('.$relationId.');way(around:1000)["amenity"~"monastery|place_of_worship|ruins"];
rel('.$relationId.');way(around:1000)["historic"~"castle|archaeological_site|tower|city_gate|ruins"];
rel('.$relationId.');way(around:1000)["building"~"castle|monastery|ruins|tower"];
rel('.$relationId.');way(around:1000)["religion"="christian"];
rel('.$relationId.');way(around:1000)["man_made"="tower"];
);
rel('.$relationId.');rel(around:1000)["amenity"~"monastery|place_of_worship|ruins"];
rel('.$relationId.');rel(around:1000)["historic"~"castle|archaeological_site|tower|city_gate|ruins"];
rel('.$relationId.');rel(around:1000)["building"~"castle|monastery|ruins|tower"];
rel('.$relationId.');rel(around:1000)["religion"="christian"];
rel('.$relationId.');rel(around:1000)["man_made"="tower"];
);
(._;>;);
out;


';
        }

        return [
            Textarea::make(__('Overpass Query'), 'overpass_query')
                ->rows(15)
                ->help(__('Enter the Overpass query to execute. You can test the query at <a href="https://overpass-turbo.eu/" target="_blank">https://overpass-turbo.eu/</a>'))
                ->default($query),
        ];
    }
}
