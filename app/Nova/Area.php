<?php

namespace App\Nova;

use Laravel\Nova\Nova;
use App\Models\HikingRoute;
use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use App\Nova\Actions\DownloadKml;
use App\Nova\Actions\DownloadShape;
use App\Nova\Actions\downloadGeojson;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\HikingRoutesAreaFilter;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class Area extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Area>
     */
    public static $model = \App\Models\Area::class;

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
        'code',
        'full_code',
    ];

    private static array $indexDefaultOrder = [
        'name' => 'asc',
    ];

    public static function label(): string
    {
        return 'Aree';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        if (auth()->user()->hasRole('Administrator')) {
            return $query;
        }

        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];
            $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        return $query->whereHas('users', function ($query) {
            $query->where('users.id', auth()->id());
        });
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
            Text::make(__('Name'), 'name')->sortable(),
            Text::make(__('Code'), 'code')->sortable(),
            Text::make(__('Full code'), 'full_code')->sortable(),
            Text::make(__('Region'), 'province_id', function () {
                return $this->province->region->name ?? null;
            }),
            Text::make(__('Province'), 'province_id', function () {
                return $this->province->name ?? null;
            }),
            Text::make(__('Sectors'), 'sectors', function () {
                return count($this->sectors) ?? 0;
            }),
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
        if (! is_null($request['resourceId'])) {
            $area = \App\Models\Area::find($request['resourceId']);
            $tot = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $hikingRoutes = $area->hikingRoutes;

            foreach ($hikingRoutes as $hr) {
                $hrStatus = HikingRoute::find($hr->id)->osm2cai_status;
                $tot[$hrStatus]++;
            }

            $sal = $area->getSal();

            return [
                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.area-stats-card', [
                        'value' => $area->manager,
                        'label' => 'Responsabili di settore',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.area-sal-card', [
                        'value' => number_format($sal * 100, 2),
                        'label' => 'SAL',
                        'backgroundColor' => Osm2caiHelper::getSalColor($sal),
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.area-stats-card', [
                        'value' => $tot[3] + $tot[4],
                        'label' => 'Numero percorsi sda 3/4',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.area-stats-card', [
                        'value' => $area->num_expected,
                        'label' => 'Numero percorsi attesi',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),
                $this->getSdaCard(1, $tot[1]),
                $this->getSdaCard(2, $tot[2]),
                $this->getSdaCard(3, $tot[3]),
                $this->getSdaCard(4, $tot[4]),
            ];
        }

        return [];
    }

    private function getSdaCard(int $sda, int $num): HtmlCard
    {
        $exploreUrl = '';
        if ($num > 0) {
            $resourceId = request()->get('resourceId');
            $filter = base64_encode(json_encode([
                ['class' => HikingRoutesAreaFilter::class, 'value' => $resourceId],
            ]));
            $exploreUrl = trim(Nova::path(), '/') . "/resources/hiking-routes/lens/hiking-routes-status-$sda-lens?hiking-routes_filter=$filter";
        }

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.area-sda-card', [
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
        return [
            (new downloadGeojson())->canRun(function () {
                return true;
            })->onlyInline(),
            (new DownloadShape())->canRun(function () {
                return true;
            })->onlyInline(),
            (new DownloadKml())->canRun(function () {
                return true;
            })->onlyInline(),
        ];
    }
}
