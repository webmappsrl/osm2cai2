<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Actions\BulkSectorsModeratorAssignAction;
use App\Nova\Actions\DownloadCsvCompleteAction;
use App\Nova\Actions\DownloadGeojson;
use App\Nova\Actions\DownloadKml;
use App\Nova\Actions\DownloadShape;
use App\Nova\Actions\SectorAssignModerator;
use App\Nova\Actions\UploadSectorGeometryAction;
use App\Nova\Filters\AreaFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Filters\RegionFilter;
use App\Nova\Lenses\NoNameSectorsColumnsLens;
use App\Nova\Lenses\NoNumExpectedColumnsLens;
use App\Nova\Lenses\NoResponsabileSectorsColumnsLens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Wm\MapMultiPolygon\MapMultiPolygon;

class Sector extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Sector>
     */
    public static $model = \App\Models\Sector::class;

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
        'name',
        'human_name',
        'code',
        'full_code',
    ];

    private static $indexDefaultOrder = [
        'name' => 'asc',
    ];

    // default order by name asc
    public static function indexQuery(NovaRequest $request, $query)
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];

            $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        return $query->ownedBy(auth()->user());
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make(__('Codice'), 'name')->sortable()->hideWhenUpdating()->required(),
            Text::make(__('Name'), 'human_name')
                ->sortable()
                ->help('Modifica il nome del settore')->required()
                ->rules('max:254'),
            Text::make(__('Code'), 'code')->sortable()->required()->rules('max:1'),
            Text::make(__('Responsabili'), 'manager')->hideFromIndex(),
            Number::make(__('Numero Atteso'), 'num_expected')->required(),
            Text::make(__('Full code'), 'full_code')->readonly(),
            Text::make(__('Region'), 'area_id', function () {
                return $this->area->province->region->name ?? '';
            })->hideWhenUpdating()->hideWhenCreating(),
            Text::make(__('Province'), 'area_id', function () {
                return $this->area->province->name ?? '';
            })->hideWhenUpdating()->hideWhenCreating(),
            Text::make(__('Area'), 'area_id', function () {
                return $this->area->name ?? '';
            })->hideWhenUpdating()->hideWhenCreating(),
            BelongsToMany::make('Users', 'moderators')
                ->searchable(),
            BelongsTo::make('Area')->onlyOnForms(),
            File::make('Geometry')->store(function (Request $request, $model) {
                return $model->fileToGeometry($request->geometry->get());
            })->onlyOnForms()->hideWhenUpdating()->required(),
            MapMultiPolygon::make('geometry')->withMeta([
                'center' => ['42.795977075', '10.326813853'],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            ])->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(Request $request)
    {
        if (! is_null($request['resourceId'])) {
            $sector = self::find($request['resourceId']);

            $numbers = DB::table('hiking_route_sector')
                ->join('hiking_routes', 'hiking_route_sector.hiking_route_id', '=', 'hiking_routes.id')
                ->where('hiking_route_sector.sector_id', $request['resourceId'])
                ->selectRaw('
                COUNT(CASE WHEN hiking_routes.osm2cai_status = 1 THEN 1 END) as tot1,
                COUNT(CASE WHEN hiking_routes.osm2cai_status = 2 THEN 1 END) as tot2, 
                COUNT(CASE WHEN hiking_routes.osm2cai_status = 3 THEN 1 END) as tot3,
                COUNT(CASE WHEN hiking_routes.osm2cai_status = 4 THEN 1 END) as tot4
            ')
                ->first();

            $numbers = [
                1 => $numbers->tot1,
                2 => $numbers->tot2,
                3 => $numbers->tot3,
                4 => $numbers->tot4,
            ];

            $sal = $sector->getSal();

            return [
                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.sector-manager-card', [
                        'manager' => $sector->manager,
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.sector-sal-card', [
                        'sal' => number_format($sal * 100, 2),
                        'backgroundColor' => Osm2caiHelper::getSalColor($sal),
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.sector-stats-card', [
                        'value' => $numbers[3] + $numbers[4],
                        'label' => 'Numero percorsi sda 3/4',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.sector-stats-card', [
                        'value' => $sector->num_expected,
                        'label' => 'Numero percorsi attesi',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                $this->getSdaSectorCard(1, $numbers[1], $request),
                $this->getSdaSectorCard(2, $numbers[2], $request),
                $this->getSdaSectorCard(3, $numbers[3], $request),
                $this->getSdaSectorCard(4, $numbers[4], $request),
            ];
        }

        return [];
    }

    private function getSdaSectorCard(int $sda, int $num, NovaRequest $request): HtmlCard
    {
        $exploreUrl = '';
        if ($num > 0) {
            $resourceId = $request->get('resourceId');

            // Get filters from HikingRoute resource
            $hikingRouteResource = new HikingRoute;
            $availableFilters = collect($hikingRouteResource->filters($request))
                ->map(function ($filter) {
                    return [get_class($filter) => ''];
                })
                ->toArray();

            // Set the value only for the sector filter
            foreach ($availableFilters as &$filter) {
                if (key($filter) === Filters\SectorFilter::class) {
                    $filter[Filters\SectorFilter::class] = $resourceId;
                }
            }

            // Encode filters to base64
            $filter = base64_encode(json_encode($availableFilters));

            // Build the URL
            $link = trim(Nova::path(), '/') . '/resources/hiking-routes/lens/hiking-routes-status-' . $sda . '-lens?hiking-routes_filter=' . $filter;
            $exploreUrl = $link;
        }

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.sector-sda-card', [
                'sda' => $sda,
                'num' => $num,
                'backgroundColor' => Osm2caiHelper::getSdaColor($sda),
                'exploreUrl' => $exploreUrl,
            ])
            ->center()
            ->withBasicStyles()
            ->onlyOnDetail();
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        $filters = [
            (new RegionFilter()),
            (new ProvinceFilter()),
            (new AreaFilter()),
        ];

        if (auth()->user()->hasRole('Regional Referent')) {
            unset($filters[0]);
        }

        return $filters;
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [
            new NoResponsabileSectorsColumnsLens,
            new NoNameSectorsColumnsLens,
            new NoNumExpectedColumnsLens,
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            (new DownloadGeojson())->canRun(function ($request) {
                return true;
            }),
            (new DownloadShape())->canRun(function ($request) {
                return true;
            }),
            (new DownloadKml())->canRun(function ($request) {
                return true;
            }),
            (new BulkSectorsModeratorAssignAction)->canSee(function ($request) {
                $rolesAllowed = ['Administrator', 'National Referent', 'Regional Referent'];

                $userRoles = auth()->user()->roles->pluck('name')->toArray();

                return count(array_intersect($rolesAllowed, $userRoles)) > 0;
            }),
            (new UploadSectorGeometryAction)
                ->confirmText('Inserire un file con la nuova geometria del settore.')
                ->confirmButtonText('Aggiorna geometria')
                ->cancelButtonText('Annulla')
                ->canSee(function ($request) {
                    return auth()->user()->hasRole('Administrator');
                })
                ->canRun(function ($request, $user) {
                    return auth()->user()->hasRole('Administrator');
                }),
            (new DownloadCsvCompleteAction)->canRun(function ($request, $zone) {
                return true;
            }),
            (new SectorAssignModerator)->canRun(function ($request, $user) {
                return auth()->user()->hasRole('Regional Referent') || auth()->user()->hasRole('Administrator') || auth()->user()->hasRole('National Referent');
            }),
        ];
    }

    public function authorizedToAttachAny(NovaRequest $request, $model)
    {
        $user = auth()->user();
        $sector = $model;

        if ($user->hasRole('Administrator') || $user->hasRole('National Referent')) {
            return true;
        }

        if ($user->region_id && $sector->area->province->region->id === $user->region_id) {
            return true;
        }

        return false;
    }
}
