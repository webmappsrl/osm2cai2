<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use App\Enums\IssuesStatusEnum;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;

class Club extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Club>
     */
    public static $model = \App\Models\Club::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'cai_code',
    ];

    public static function label()
    {
        return 'Clubs';
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $hikingRoutes = $this->hikingRoutes;

        //define the hiking routes for each osm2cai status
        $hikingRoutesSDA1 = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->osm2cai_status == 1);
        $hikingRoutesSDA2 = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->osm2cai_status == 2);
        $hikingRoutesSDA3 = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->osm2cai_status == 3);
        $hikingRoutesSDA4 = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->osm2cai_status == 4);

        //define the hikingroutes for each issue status
        $hikingRoutesSPS = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Unknown);
        $hikingRoutesSPP = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Open);
        $hikingRouteSPPP = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::PartiallyClosed);
        $hikingRoutesSPNP = $hikingRoutes->filter(fn($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Closed);

        return [
            ID::make()->sortable()
                ->hideFromIndex(),
            Text::make('Nome', 'name',)
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 50, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);

                    return $htmlName;
                })
                ->asHtml(),
            Text::make('Codice CAI', 'cai_code')
                ->sortable()
                ->rules('required', 'max:255'),
            BelongsTo::make('Region', 'region', Region::class)
                ->searchable(),
            Text::make('SDA1', function () use ($hikingRoutesSDA1) {
                return $hikingRoutesSDA1->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SDA2', function () use ($hikingRoutesSDA2) {
                return $hikingRoutesSDA2->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SDA3', function () use ($hikingRoutesSDA3) {
                return $hikingRoutesSDA3->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SDA4', function () use ($hikingRoutesSDA4) {
                return $hikingRoutesSDA4->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('TOT', function () use ($hikingRoutes) {
                return $hikingRoutes->sum(function ($hikingRoute) {
                    return ($hikingRoute->osm2cai_status < 5 && $hikingRoute->osm2cai_status > 0) ? 1 : 0;
                });
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SPS', function () use ($hikingRoutesSPS) {
                return $hikingRoutesSPS->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SPP', function () use ($hikingRoutesSPP) {
                return $hikingRoutesSPP->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SPPP', function () use ($hikingRouteSPPP) {
                return $hikingRouteSPPP->count();
            })->onlyOnIndex()
                ->sortable(),
            Text::make('SPNP', function () use ($hikingRoutesSPNP) {
                return $hikingRoutesSPNP->count();
            })->onlyOnIndex()
                ->sortable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
