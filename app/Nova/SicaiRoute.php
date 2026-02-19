<?php

namespace App\Nova;

use App\Models\SicaiRoute as SicaiRouteModel;
use App\Nova\Cards\LinksCard;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class SicaiRoute extends HikingRoute
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<SicaiRouteModel>
     */
    public static $model = SicaiRouteModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Routes');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Route');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {


        // Filtra solo le routes con app_id = 2
        $query->where('app_id', 2);


        return $query;
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        // Ottieni le cards dal parent (HikingRoute)
        // Il parent cerca HikingRouteModel, ma noi siamo SicaiRouteModel
        // Quindi sovrascriviamo per usare il modello corretto
        if ($request->resourceId) {
            $sicaiRoute = SicaiRouteModel::find($request->resourceId);

            if ($sicaiRoute) {
                $linksCardData = $sicaiRoute->getDataForNovaLinksCard();

                return [
                    (new \App\Nova\Cards\RefCard($sicaiRoute))->onlyOnDetail(),
                    (new LinksCard($linksCardData))->onlyOnDetail(),
                    (new \App\Nova\Cards\Osm2caiStatusCard($sicaiRoute))->onlyOnDetail(),
                ];
            }
        }

        return [];
    }

    public function actions(NovaRequest $request): array
    {
        return [];
    }

    public function fields(NovaRequest $request): array
    {
        $fields = parent::fields($request);

        // Aggiungi campo per il link al parent hiking route
        $parentRouteField = Text::make(__('Parent Hiking Route'), function () {
            $parentHikingRouteId = $this->parent_hiking_route_id;

            if (!$parentHikingRouteId) {
                return __('Not set');
            }

            $url = "/resources/hiking-routes/{$parentHikingRouteId}";
            return "<a href='{$url}' target='_blank' style='color: #2697bc; text-decoration: none; font-weight: bold;'>{$parentHikingRouteId}</a>";
        })->asHtml();

        // Aggiungi il campo all'inizio dell'array
        array_unshift($fields, $parentRouteField);

        return $fields;
    }
}
