<?php

namespace App\Nova;

use App\Enums\IssuesStatusEnum;
use App\Helpers\Osm2caiHelper;
use App\Models\Club as ModelsClub;
use App\Nova\Actions\AddMembersToClub;
use App\Nova\Actions\AssignClubManager;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\DownloadCsvCompleteAction;
use App\Nova\Actions\DownloadGeojson;
use App\Nova\Actions\FindClubHrAssociationAction;
use App\Nova\Actions\RemoveMembersFromClub;
use App\Nova\Filters\ClubFilter;
use App\Nova\Filters\RegionFilter;
use App\Nova\Metrics\ClubSalPercorribilità;
use App\Nova\Metrics\ClubSalPercorsi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class Club extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ModelsClub>
     */
    public static $model = ModelsClub::class;

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
        $hikingRoutesSDA1 = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->osm2cai_status == 1);
        $hikingRoutesSDA2 = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->osm2cai_status == 2);
        $hikingRoutesSDA3 = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->osm2cai_status == 3);
        $hikingRoutesSDA4 = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->osm2cai_status == 4);

        //define the hikingroutes for each issue status
        $hikingRoutesSPS = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Unknown);
        $hikingRoutesSPP = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Open);
        $hikingRouteSPPP = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::PartiallyClosed);
        $hikingRoutesSPNP = $hikingRoutes->filter(fn ($hikingRoute) => $hikingRoute->issues_status == IssuesStatusEnum::Closed);

        return [
            ID::make()->sortable()
                ->hideFromIndex(),
            Text::make('Nome', 'name', )
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 50, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);

                    return $htmlName;
                })
                ->asHtml(),
            Text::make('CAI code', 'cai_code')
                ->sortable()
                ->rules('required', 'max:255'),
            BelongsTo::make('Region', 'region', Region::class)
                ->searchable(),
            Text::make('Club\'s managers', function () {
                return $this->formatUserList($this->managerUsers()->get(), null, false);
            })->asHtml(),
            Text::make('Club\'s members', function () {
                return $this->formatUserList($this->users()->get(), null, true);
            })->asHtml()
                ->onlyOnDetail(),
            BelongsToMany::make('Club\'s hiking routes', 'hikingRoutes', HikingRoute::class)
                ->help(__('Only national referents can add hiking routes to the club')),
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
        $clubId = $request->resourceId;

        $club = ModelsClub::where('id', $clubId)->first();
        $hr = $club ? $club->hikingRoutes()->get() : [];
        if (! auth()->user()->hasRole('Administrator') && auth()->user()->club_id != null && auth()->user()->region_id != null) {
            $userClub = ModelsClub::where('id', auth()->user()->club_id)->first();
            $numbers[1] = $userClub->hikingRoutes()->where('osm2cai_status', 1)->count();
            $numbers[2] = $userClub->hikingRoutes()->where('osm2cai_status', 2)->count();
            $numbers[3] = $userClub->hikingRoutes()->where('osm2cai_status', 3)->count();
            $numbers[4] = $userClub->hikingRoutes()->where('osm2cai_status', 4)->count();
        } else {
            $values = DB::table('hiking_routes')
                ->join('hiking_route_club', 'hiking_routes.id', '=', 'hiking_route_club.hiking_route_id')
                ->where('hiking_route_club.club_id', $clubId)
                ->select('hiking_routes.osm2cai_status', DB::raw('count(*) as num'))
                ->groupBy('hiking_routes.osm2cai_status')
                ->get();

            $numbers = [];
            $numbers[1] = 0;
            $numbers[2] = 0;
            $numbers[3] = 0;
            $numbers[4] = 0;

            if (count($values) > 0) {
                foreach ($values as $value) {
                    $numbers[$value->osm2cai_status] = $value->num;
                }
            }
        }

        $tot = array_sum($numbers);

        $cards = [
            $this->getSdaClubCard(1, $numbers[1], $request),
            $this->getSdaClubCard(2, $numbers[2], $request),
            $this->getSdaClubCard(3, $numbers[3], $request),
            $this->getSdaClubCard(4, $numbers[4], $request),
            (new ClubSalPercorribilità($hr))->onlyOnDetail()->width('1/4'),
            (new ClubSalPercorsi($hr))->onlyOnDetail()->width('1/4'),
            (new HtmlCard())->width('1/4')
                ->view('nova.cards.club-total-hr-card', [
                    'total' => count($hr),
                ])->center()
                ->withBasicStyles()
                ->onlyOnDetail(),
        ];

        if (count($hr) > 0) {
            $cards[] = (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.club-distance-card', [
                    'totalDistance' => $hr->sum(function ($item) {
                        $distance = $item->osmfeatures_data['properties']['dem_enrichment']['distance'] ?? $item->osmfeatures_data['properties']['distance'] ?? 0;
                        return (float) $distance;
                    }),
                ])
                ->center()
                ->withBasicStyles()
                ->onlyOnDetail();
        }

        return $cards;
    }

    private function getSdaClubCard(int $sda, int $num, NovaRequest $request): HtmlCard
    {
        $exploreUrl = '';
        if ($num > 0) {
            $resourceId = $request->get('resourceId');
            $filter = base64_encode(json_encode([
                ['class' => ClubFilter::class, 'value' => $resourceId],
            ]));
            $exploreUrl = trim(Nova::path(), '/')."/resources/hiking-routes/lens/hiking-routes-status-$sda-lens?hiking-routes_filter=$filter";
        }

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.club-sda-card', [
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
        return [(new RegionFilter())->canSee(function ($request) {
            return true;
        })];
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
    public function actions(Request $request): array
    {
        return [
            (new FindClubHrAssociationAction())
                ->canSee(function ($request) {
                    return $request->user()->hasRole('Administrator');
                })
                ->canRun(function ($request) {
                    return true;
                }),
            (new AssignClubManager())
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request) {
                    return true;
                }),
            (new AddMembersToClub())
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request) {
                    return true;
                }),
            (new RemoveMembersFromClub())
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request) {
                    return true;
                }),
            (new DownloadGeojson())->canSee(function ($request) {
                return true;
            })->canRun(function ($request) {
                return true;
            })->showInline(),
            (new DownloadCsvCompleteAction)->canSee(function ($request) {
                return true;
            })->canRun(function ($request) {
                return true;
            })->showInline(),
            (new CacheMiturApi('Club'))->canSee(function ($request) {
                return $request->user()->hasRole('Administrator');
            })->canRun(function ($request) {
                return $request->user()->hasRole('Administrator');
            }),
        ];
    }

    public function authorizedToAttachAny(NovaRequest $request, $model)
    {
        return $request->user()->hasRole('Administrator') || $request->user()->hasRole('National referent') || $request->user()->managedClub?->id === $this->model()->id;
    }

    public function authorizedToDetach(NovaRequest $request, $model, $relationship)
    {
        return $request->user()->hasRole('Administrator') || $request->user()->hasRole('National referent');
    }

    /**
     * Format a list of users into an HTML string
     *
     * @param \Illuminate\Database\Eloquent\Collection $users
     * @param int|null $maxLength Maximum length of each name
     * @param bool $showCount Whether to show the total count
     * @return string
     */
    private function formatUserList($users, $maxLength = null, $showCount = false)
    {
        if ($users->isEmpty()) {
            return '-';
        }

        $formattedNames = $users->map(function ($user) use ($maxLength) {
            $name = $user->name;
            if ($maxLength && strlen($name) > $maxLength) {
                $name = substr($name, 0, $maxLength).'...';
            }

            return $name;
        })->join('<br>');

        if ($showCount) {
            $formattedNames .= '<br><strong>Total: '.$users->count().'</strong>';
        }

        return $formattedNames;
    }
}
