<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Actions\DownloadGeojson;
use App\Nova\Actions\DownloadKml;
use App\Nova\Actions\DownloadShape;
use App\Nova\Filters\HikingRoutesProvinceFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Filters\RegionFilter;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Wm\MapMultiPolygon\MapMultiPolygon;

class Province extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Province>
     */
    public static $model = \App\Models\Province::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    public static function label()
    {
        return 'Province';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'osmfeatures_id',
        'code',
        'osmfeatures_data->properties->osm_tags->short_name',
        'osmfeatures_data->properties->osm_tags->ref',
    ];

    private static $indexDefaultOrder = [
        'name' => 'asc',
    ];

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
        $areasCount = cache()->remember('province_'.$this->id.'_areas_count', 60 * 60 * 24, function () {
            return count($this->areas);
        });
        $sectorsCount = cache()->remember('province_'.$this->id.'_sectors_count', 60 * 60 * 24, function () {
            return 0;
        });
        $code = cache()->remember('province_'.$this->id.'_code', 60 * 60 * 24, function () {
            return $this->osmfeatures_data['properties']['osm_tags']['short_name'] ?? $this->osmfeatures_data['properties']['osm_tags']['ref'] ?? '';
        });

        foreach ($this->areas as $area) {
            $sectorsCount += count($area->sectors);
        }

        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Text::make('Code', function () use ($code) {
                return $code;
            })->sortable(),
            Text::make('Full Code', function () use ($code) {
                //if code is not null, add the region code
                if ($code) {
                    $code = $this->region ? $this->region->code.'.'.$code : $code;
                }

                return $code;
            })->sortable(),
            Number::make('Areas', function () use ($areasCount) {
                return $areasCount;
            })->sortable(),
            Number::make('Sectors', function () use ($sectorsCount) {
                return $sectorsCount;
            })->sortable(),
            BelongsTo::make('Region', 'region', Region::class),
            DateTime::make('Created At', 'created_at')->hideFromIndex(),
            DateTime::make('Updated At', 'updated_at')->hideFromIndex(),
            Text::make('Osmfeatures ID', function () {
                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml(),
            DateTime::make('Osmfeatures updated at', 'osmfeatures_updated_at')->sortable(),
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
            $province = self::find($request['resourceId']);

            $data = DB::table('hiking_route_province')
                ->join('hiking_routes', 'hiking_route_province.hiking_route_id', '=', 'hiking_routes.id')
                ->where('hiking_route_province.province_id', $request['resourceId'])
                ->select('hiking_routes.osm2cai_status', DB::raw('COUNT(*) as total'))
                ->groupBy('hiking_routes.osm2cai_status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->osm2cai_status => $item->total];
                });

            $numbers[1] = $data[1] ?? 0;
            $numbers[2] = $data[2] ?? 0;
            $numbers[3] = $data[3] ?? 0;
            $numbers[4] = $data[4] ?? 0;

            $sal = $province->getSal();

            return [
                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.province-stats-card', [
                        'value' => $province->manager,
                        'label' => 'Responsabili di settore',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.province-sal-card', [
                        'value' => number_format($sal * 100, 2),
                        'label' => 'SAL',
                        'backgroundColor' => Osm2caiHelper::getSalColor($sal),
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.province-stats-card', [
                        'value' => $numbers[3] + $numbers[4],
                        'label' => 'Numero percorsi sda 3/4',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                (new HtmlCard())
                    ->width('1/4')
                    ->view('nova.cards.province-stats-card', [
                        'value' => $province->num_expected,
                        'label' => 'Numero percorsi attesi',
                    ])
                    ->center()
                    ->withBasicStyles()
                    ->onlyOnDetail(),

                $this->getSdaProvinceCard(1, $numbers[1], $request),
                $this->getSdaProvinceCard(2, $numbers[2], $request),
                $this->getSdaProvinceCard(3, $numbers[3], $request),
                $this->getSdaProvinceCard(4, $numbers[4], $request),
            ];
        }

        return [];
    }

    private function getSdaProvinceCard(int $sda, int $num, NovaRequest $request): HtmlCard
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

            // Set the value only for the province filter
            foreach ($availableFilters as &$filter) {
                if (key($filter) === ProvinceFilter::class) {
                    $filter[ProvinceFilter::class] = $resourceId;
                }
            }

            // Encode filters to base64
            $filter = base64_encode(json_encode($availableFilters));

            // Build the URL
            $link = trim(Nova::path(), '/').'/resources/hiking-routes/lens/hiking-routes-status-'.$sda.'-lens?hiking-routes_filter='.$filter;
            $exploreUrl = $link;
        }

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.province-sda-card', [
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
        return [(new RegionFilter())];
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
            (new DownloadGeojson())->canRun(function ($request, $zone) {
                return true;
            }),
            (new DownloadShape())->canRun(function ($request, $zone) {
                return true;
            }),
            (new DownloadKml())->canRun(function ($request, $zone) {
                return true;
            }),
        ];
    }
}
