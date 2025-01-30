<?php

namespace App\Helpers\Nova;

use App\Helpers\Osm2caiHelper;
use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\UgcPoi;
use App\Models\User;
use App\Nova\Metrics\AcquaSorgenteTrend;
use App\Nova\Metrics\IssueLastUpdatePerMonth;
use App\Nova\Metrics\IssueStatusPartition;
use App\Nova\Metrics\TotalUsers;
use App\Nova\Metrics\UserDistributionByRegion;
use App\Nova\Metrics\UserDistributionByRole;
use App\Nova\Metrics\ValidatedHrPerMonth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Mako\CustomTableCard\CustomTableCard;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\Table\Row;

class DashboardCardsHelper
{
    public function getNationalSalCard()
    {
        $sal = Cache::remember('national_sal', now()->addDays(2), function () {
            return (HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
                HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
                HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
                HikingRoute::where('osm2cai_status', 4)->count()
            ) / Region::sum('num_expected');
        });

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.sal-nazionale', [
                'sal' => $sal,
                'backgroundColor' => Osm2caiHelper::getSalColor($sal),
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda3Sda4Card()
    {
        return $this->getTotalKmCard([3, 4], 'Totale km #sda3 e #sda4');
    }

    public function getTotalKmSda4Card()
    {
        return $this->getTotalKmCard(4, 'Totale km #sda4');
    }

    public function getNoPermissionsCard()
    {
        return (new HtmlCard())
            ->view('nova.cards.no-permissions-card')
            ->center()
            ->withBasicStyles();
    }

    public function getItalyDashboardCards()
    {
        $numbers = Cache::remember('italy_dashboard_data', now()->addDays(2), function () {
            $values = DB::table('hiking_routes')
                ->select('osm2cai_status', DB::raw('count(*) as num'))
                ->groupBy('osm2cai_status')
                ->get();

            $numbers = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach ($values as $value) {
                $numbers[$value->osm2cai_status] = $value->num;
            }

            return $numbers;
        });

        $tot = array_sum($numbers);

        return [
            'italy-total' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-total', ['tot' => $tot])
                ->center()
                ->withBasicStyles(),
            'sda1' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[1],
                    'sda' => 1,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(1),
                ])
                ->center()
                ->withBasicStyles(),

            'sda2' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[2],
                    'sda' => 2,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(2),
                ])
                ->center()
                ->withBasicStyles(),

            'sda3' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[3],
                    'sda' => 3,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(3),
                ])
                ->center()
                ->withBasicStyles(),

            'sda4' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[4],
                    'sda' => 4,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(4),
                ])
                ->center()
                ->withBasicStyles(),
        ];
    }

    public function getPercorsiFavoritiDashboardCard()
    {
        $regions = Cache::remember('percorsi-favoriti-dashboard-data', 60 * 60 * 24 * 2, function () {
            return DB::table('regions')
                ->select([
                    'regions.name as region_name',
                    DB::raw('(SELECT COUNT(*) FROM hiking_route_region hrr JOIN hiking_routes hr ON hrr.hiking_route_id = hr.id WHERE hrr.region_id = regions.id AND hr.region_favorite = true) as favorite_routes_count'),
                    DB::raw('(SELECT COUNT(*) FROM hiking_route_region hrr JOIN hiking_routes hr ON hrr.hiking_route_id = hr.id WHERE hrr.region_id = regions.id AND hr.osm2cai_status = 4) as sda4_routes_count'),
                ])
                ->orderByDesc('favorite_routes_count')
                ->get();
        });

        return (new HtmlCard())->width('full')
            ->view('nova.cards.percorsi-favoriti-table', ['regions' => $regions])
            ->withBasicStyles();
    }

    public function getEcPoisDashboardCard()
    {
        $ecPoisCount = Cache::remember('ec-pois-count', 60 * 60 * 24 * 2, function () {
            return \App\Models\EcPoi::count();
        });

        return (new HtmlCard())->view('nova.cards.ec-pois', ['ecPoiCount' => $ecPoisCount])->center()->withBasicStyles();
    }

    public function getMainDashboardCards()
    {
        $cards = [];

        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        switch ($roles) {
            case in_array('Administrator', $roles):
                $cards = $this->nationalCards($user, $roles);
                break;
            case in_array('National Referent', $roles):
                $cards = $this->nationalCards($user, $roles);
                break;
            case in_array('Regional Referent', $roles):
                $cards = $this->regionalCards($user, $roles);
                break;
            case in_array('Local Referent', $roles):
                if ($user->sectors->count()) {
                    $cards = $this->_localCardsByModelClassName($user, Sector::class);
                } elseif ($user->areas->count()) {
                    $cards = $this->_localCardsByModelClassName($user, Area::class);
                } else {
                    $cards = $this->_localCardsByModelClassName($user, Province::class);
                }
                break;
            default:
                $cards = [$this->dashboardCardsHelper->getNoPermissionsCard()];
                break;
        }

        return $cards;
    }

    public function getUtentiDashboardCards()
    {
        $usersByRole = Cache::remember('usersByRole', 60 * 60 * 24 * 2, function () {
            return
                DB::table('users')
                ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name', DB::raw('count(*) as count'))
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'name')
                ->toArray();
        });

        $usersByRegion = Cache::remember('usersByRegion', 60 * 60 * 24 * 2, function () {
            return
                [
                    'Region' => DB::table('users')->whereNotNull('region_id')->count(),
                    'Province' => DB::table('province_user')->distinct('user_id')->count('user_id'),
                    'Area' => DB::table('area_user')->distinct('user_id')->count('user_id'),
                    'Sector' => DB::table('sector_user')->distinct('user_id')->count('user_id'),
                ];
        });

        $mostActiveUsers = Cache::remember('mostActiveUsers', 60 * 60 * 24 * 2, function () {
            return DB::select("
                SELECT u.id AS user_id, u.name AS user_name, COUNT(DISTINCT hr.id) AS numero_validazioni
                FROM users u
                JOIN hiking_routes hr ON u.id = hr.validator_id
                WHERE hr.osm2cai_status = '4'
                GROUP BY u.id, u.name
                ORDER BY numero_validazioni DESC
                LIMIT 5
            ");
        });

        return [
            new TotalUsers,
            new UserDistributionByRole($usersByRole),
            new UserDistributionByRegion($usersByRegion),
            (new HtmlCard())
                ->width('1/2')
                ->view('nova.cards.most-active-users', ['users' => $mostActiveUsers])
                ->withBasicStyles(),
            (new ValidatedHrPerMonth()),
            (new IssueLastUpdatePerMonth()),
        ];
    }

    public function getPercorribilitàDashboardCards(User $user = null)
    {
        $hikingRoutesSda4 = $this->getHikingRoutes(4, $user);
        $hikingRoutesSda34 = $this->getHikingRoutes([3, 4], $user);

        return [
            new IssueStatusPartition($hikingRoutesSda4, 'Percorribilità SDA 4', 'sda4-issue-status-partition'),
            new IssueStatusPartition($hikingRoutesSda34, 'Percorribilità SDA 3 e 4', 'sda3-and-4-issue-status-partition'),
        ];
    }

    public function getSALMiturAbruzzoDashboardCards()
    {
        $regions = Cache::remember('sal_mitur_regions', 60 * 60 * 24 * 2, function () {
            return Region::all();
        });

        $totalsGlobal = Cache::remember('sal_mitur_totals', 60 * 60 * 24 * 2, function () {
            $sumMountainGroups = DB::select('SELECT count(*) as count FROM mountain_groups')[0]->count;
            $sumEcPois = DB::select('SELECT count(*) as count FROM ec_pois')[0]->count;
            $sumHikingRoutes = DB::select('SELECT count(*) as count FROM hiking_routes WHERE osm2cai_status = 4')[0]->count;
            $sumPoiTotal = $sumEcPois + $sumHikingRoutes;
            $sumCaiHuts = DB::select('SELECT count(*) as count FROM cai_huts')[0]->count;
            $sumClubs = DB::select('SELECT count(*) as count FROM clubs')[0]->count;

            return [
                'sumMountainGroups' => $sumMountainGroups,
                'sumEcPois' => $sumEcPois,
                'sumHikingRoutes' => $sumHikingRoutes,
                'sumPoiTotal' => $sumPoiTotal,
                'sumCaiHuts' => $sumCaiHuts,
                'sumClubs' => $sumClubs,
            ];
        });

        return [
            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.sal-mitur-abruzzo-regions-table', [
                    'regions' => $regions,
                    'totals' => $totalsGlobal,
                ])
                ->withBasicStyles(),
        ];
    }

    public function getAcquaSorgenteDashboardCards()
    {
        $ugcPoiWaterCount = Cache::remember('ugc_poi_water_count', now()->addDays(2), function () {
            return UgcPoi::where('form_id', 'water')->count();
        });

        return [
            (new HtmlCard())->view('nova.cards.acqua-sorgente', ['ugcPoiWaterCount' => $ugcPoiWaterCount])->center()->withBasicStyles(),
            (new AcquaSorgenteTrend)->width('1/2'),
        ];
    }

    public function getSectorsDashboardCards()
    {
        $user = auth()->user();
        // Get sectors_id
        $sectorsIds = Cache::remember('sectors_dashboard_ids', now()->addDays(2), function () use ($user) {
            $ids = [];
            foreach ($user->region->provinces as $province) {
                if (Arr::accessible($province->areas)) {
                    foreach ($province->areas as $area) {
                        if (Arr::accessible($area->sectors)) {
                            $ids = array_merge($ids, $area->sectors->pluck('id')->toArray());
                        }
                    }
                }
            }

            return $ids;
        });

        // Query to get sectors with their hiking route counts by osm2cai_status
        $items = Cache::remember('sectors_dashboard_items', now()->addDays(2), function () use ($sectorsIds) {
            return DB::table('sectors')
                ->select(
                    'sectors.id',
                    'sectors.full_code',
                    'sectors.num_expected',
                    DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 1 THEN 1 END) as tot1'),
                    DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 2 THEN 1 END) as tot2'),
                    DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 3 THEN 1 END) as tot3'),
                    DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 4 THEN 1 END) as tot4')
                )
                ->leftJoin('hiking_route_sector', 'hiking_route_sector.sector_id', '=', 'sectors.id')
                ->leftJoin('hiking_routes', 'hiking_routes.id', '=', 'hiking_route_sector.hiking_route_id')
                ->whereIn('sectors.id', $sectorsIds)
                ->groupBy('sectors.id', 'sectors.full_code', 'sectors.num_expected')
                ->get();
        });

        $sectors = Cache::remember('sectors_dashboard_data', now()->addDays(2), function () use ($items) {
            return $items->map(function ($item) {
                $sector = Sector::find($item->id);
                $tot = $item->tot1 + $item->tot2 + $item->tot3 + $item->tot4;
                $sal = $item->num_expected == 0 ? 0 : (($item->tot1 * 0.25) + ($item->tot2 * 0.50) + ($item->tot3 * 0.75) + ($item->tot4)) / $item->num_expected;

                return (object) [
                    'id' => $item->id,
                    'full_code' => $item->full_code,
                    'human_name' => $sector->human_name,
                    'tot1' => $item->tot1,
                    'tot2' => $item->tot2,
                    'tot3' => $item->tot3,
                    'tot4' => $item->tot4,
                    'num_expected' => $item->num_expected,
                    'sal' => $sal,
                    'sal_color' => Osm2caiHelper::getSalColor($sal),
                ];
            });
        });

        return [
            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.sectors-table', ['sectors' => $sectors])
                ->withBasicStyles(),
        ];
    }

    private function getTotalKmCard($status, $label)
    {
        $cacheKey = is_array($status) ? 'total_km_'.implode('_', $status) : 'total_km_'.$status;

        $total = Cache::remember($cacheKey, now()->addDays(2), function () use ($status) {
            $query = DB::table('hiking_routes')
                ->selectRaw('
                    COALESCE(
                        SUM(ST_Length(geometry::geography) / 1000), 
                        0
                    ) as total
                ');

            if (is_array($status)) {
                $query->whereIn('osm2cai_status', $status);
            } else {
                $query->where('osm2cai_status', $status);
            }

            $tot = $query->first();

            return round(floatval($tot->total), 2);
        });

        $formatted = number_format($total, 2, ',', '.');

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => $label,
            ])
            ->center()
            ->withBasicStyles();
    }

    private function nationalCards($user, $roles)
    {
        $data = Cache::remember('national_data', now()->addDays(2), function () {
            $values = DB::table('hiking_routes')
                ->select('osm2cai_status', DB::raw('count(*) as num'))
                ->groupBy('osm2cai_status')
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

            $totalExpected = Region::sum('num_expected');
            $sal = $totalExpected > 0 ? (
                HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
                HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
                HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
                HikingRoute::where('osm2cai_status', 4)->count()
            ) / $totalExpected : 0;

            return [
                'numbers' => $numbers,
                'sal' => $sal,
            ];
        });

        $cards = [
            (new HtmlCard())->width('1/4')->view('nova.cards.username-card', ['userName' => $user->name])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.permessi-card', ['roles' => $roles])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.last-login-card', ['lastLogin' => $user->last_login_at])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sal-nazionale', ['sal' => $data['sal'], 'backgroundColor' => Osm2caiHelper::getSalColor($data['sal'])])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $data['numbers'], 'sda' => 1, 'backgroundColor' => Osm2caiHelper::getSdaColor(1)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $data['numbers'], 'sda' => 2, 'backgroundColor' => Osm2caiHelper::getSdaColor(2)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $data['numbers'], 'sda' => 3, 'backgroundColor' => Osm2caiHelper::getSdaColor(3)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $data['numbers'], 'sda' => 4, 'backgroundColor' => Osm2caiHelper::getSdaColor(4)])->center(true)->withBasicStyles(),
        ];

        $cards[] = $this->regionsTableCard();

        return $cards;
    }

    private function regionalCards($user, $roles)
    {
        $region = $user->region;

        $data = Cache::remember('regional_data_'.$region->id, now()->addDays(2), function () use ($region) {
            $numbers = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach ($region->hikingRoutes as $hr) {
                $numbers[$hr->osm2cai_status]++;
            }

            $numAreas = 0;
            $areaCodes = [];
            $numSectors = 0;
            foreach ($region->provinces as $province) {
                array_push($areaCodes, implode(',', $province->areas->pluck('code')->toArray()));
                if ($province->areas->count() > 0) {
                    foreach ($province->areas as $area) {
                        $numSectors += $area->sectors->count();
                    }
                }
            }
            $areaCodes = implode(',', $areaCodes);
            $areaCodes = implode(',', array_unique(explode(',', $areaCodes)));
            $numAreas = count(explode(',', $areaCodes));

            $sal = $region->getSal();
            $salColor = Osm2caiHelper::getSalColor($sal);

            return [
                'numbers' => $numbers,
                'numAreas' => $numAreas,
                'numSectors' => $numSectors,
                'sal' => $sal,
                'salColor' => $salColor,
            ];
        });

        $SALIssueStatus = Cache::remember('sal_issue_status_'.$region->id, now()->addDays(2), function () use ($region) {
            return $this->getSalIssueStatus($region);
        });

        $cards = [
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.username-regional-card', ['userName' => $user->name])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.permissions-regional-card', ['permissions' => $roles])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.sal-issues-status-card', ['status' => $SALIssueStatus])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.sal-regional-card', [
                    'sal' => number_format($data['sal'] * 100, 2),
                    'backgroundColor' => $data['salColor'],
                    'regionName' => $region->name,
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.region-info-card', [
                    'regionName' => $region->name,
                    'geojsonUrl' => route('loading-download', ['type' => 'geojson-complete', 'model' => 'region', 'id' => $region->id]),
                    'shapefileUrl' => route('loading-download', ['type' => 'shapefile', 'model' => 'region', 'id' => $region->id]),
                    'csvUrl' => route('loading-download', ['type' => 'csv', 'model' => 'region', 'id' => $region->id]),
                    'lastSync' => Cache::get('last_osm_sync', '24 ore fa'),
                ])
                ->center()
                ->withBasicStyles(),

            // Stats cards
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => $region->provinces->count(),
                    'label' => '#province',
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => $data['numAreas'],
                    'label' => '#aree',
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => $data['numSectors'],
                    'label' => '#settori',
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => array_sum($data['numbers']),
                    'label' => '#tot percorsi',
                ])
                ->center()
                ->withBasicStyles(),

            // SDA cards
            $this->getSdaRegionalCard(1, $data['numbers'][1]),
            $this->getSdaRegionalCard(2, $data['numbers'][2]),
            $this->getSdaRegionalCard(3, $data['numbers'][3]),
            $this->getSdaRegionalCard(4, $data['numbers'][4]),
        ];

        $provinceCards = [];
        foreach ($region->provinces as $province) {
            $provinceCards[] = $this->getChildrenTableCardByModel($province); //areas
        }

        $cards = array_merge(
            $cards,
            [$this->getChildrenTableCardByModel($region)], //provinces
            $provinceCards,
        );

        return $cards;
    }

    /**
     * @return CustomTableCard
     */
    private function regionsTableCard(): CustomTableCard
    {
        $regionsCard = new CustomTableCard();
        $regionsCard->title(__('SDA e SAL Regioni'));

        // Headings
        $regionsCard->header([
            new Cell(__('Regione')),
            new Cell(__('#1')),
            new Cell(__('#2')),
            new Cell(__('#3')),
            new Cell(__('#4')),
            new Cell(__('#tot')),
            new Cell(__('#att')),
            new Cell(__('SAL')),
        ]);

        // Fetch regions data with caching
        $data = Cache::remember('regions_table_data', 60 * 60 * 24 * 2, function () {
            $regions = Region::all();
            $tableData = [];

            foreach ($regions as $region) {
                $hikingRoutes = $region->hikingRoutes()->get();
                $att = $region->num_expected ?? 0;

                $tot1 = 0;
                $tot2 = 0;
                $tot3 = 0;
                $tot4 = 0;

                if ($hikingRoutes->count() > 0) {
                    foreach ($hikingRoutes as $route) {
                        switch ($route['osm2cai_status'] ?? 0) {
                            case 1:
                                $tot1++;
                                break;
                            case 2:
                                $tot2++;
                                break;
                            case 3:
                                $tot3++;
                                break;
                            case 4:
                                $tot4++;
                                break;
                        }
                    }
                }

                $tot = count($hikingRoutes);

                // SAL calculation
                if ($att > 0) {
                    $sal = ($tot1 * 0.25 + $tot2 * 0.50 + $tot3 * 0.75 + $tot4) / $att;
                    $sal = min($sal, 1);
                    $salDisplay = number_format($sal * 100, 2).' %';
                } else {
                    $sal = 0;
                    $salDisplay = 'N/A';
                }

                $sal_color = Osm2caiHelper::getSalColor($sal);

                $row = new Row(
                    new Cell($region->name.($region->code ? ' ('.$region->code.')' : '')),
                    new Cell((string) $tot1),
                    new Cell((string) $tot2),
                    new Cell((string) $tot3),
                    new Cell((string) $tot4),
                    new Cell((string) $tot),
                    new Cell((string) $att),
                    new Cell('<div style="background-color: '.$sal_color.'; color: white; font-size: x-large">'.$salDisplay.'</div>'),
                );
                $tableData[] = $row;
            }

            return $tableData;
        });

        $regionsCard->data($data);

        return $regionsCard;
    }

    private function getSalIssueStatus(Region $region): string
    {
        $percorribile = 0;
        $nonPercorribile = 0;
        $percorribileParzialmente = 0;
        $hikingRoutes = $region->hikingRoutes()->get();

        if ($hikingRoutes->count() == 0) {
            return 'N/A';
        }

        foreach ($hikingRoutes as $hr) {
            switch ($hr->issues_status) {
                case 'percorribile':
                    $percorribile++;
                    break;
                case 'non percorribile':
                    $nonPercorribile++;
                case 'percorribile parzialmente':
                    $percorribileParzialmente++;
                    break;
            }
        }

        $result = (($percorribile + $percorribileParzialmente + $nonPercorribile) / count($hikingRoutes)) * 100;
        $result = round($result, 2);

        return strval($result).'%';
    }

    private function getSdaRegionalCard(int $sda, int $num): HtmlCard
    {
        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.sda-regional-card', [
                'sda' => $sda,
                'num' => $num,
                'backgroundColor' => Osm2caiHelper::getSdaColor($sda),
                'exploreUrl' => url('/resources/hiking-routes/lens/hiking-routes-status-'.$sda.'-lens'),
            ])
            ->center()
            ->withBasicStyles();
    }

    private function getChildrenTableCardByModel($model): CustomTableCard
    {
        $sectorsCard = new CustomTableCard();
        $modelName = $model->name;
        $childrenAbstractModel = $model->children()->getRelated();
        $childrenIds = $model->childrenIds();
        $childrenTable = $childrenAbstractModel->getTable();

        $sectorsCard->title("SDA e SAL $childrenTable - $modelName");

        // Headings
        $sectorsCard->header([
            new Cell($childrenTable),
            new Cell(__('Nome')),
            new Cell(__('#1')),
            new Cell(__('#2')),
            new Cell(__('#3')),
            new Cell(__('#4')),
            new Cell(__('#tot')),
            new Cell(__('#att')),
            new Cell(__('SAL')),
            new Cell(__('Actions')),
        ]);

        // Get stats with caching
        $items = Cache::remember("children_stats_{$modelName}_{$childrenTable}", 60 * 60 * 24 * 2, function () use ($childrenAbstractModel, $childrenIds) {
            return $childrenAbstractModel::getStatsForIds($childrenIds);
        });

        $data = [];
        foreach ($items as $item) {
            $tot = $item->tot1 + $item->tot2 + $item->tot3 + $item->tot4;
            $sal = $item->num_expected > 0 ?
                (($item->tot1 * 0.25) + ($item->tot2 * 0.50) + ($item->tot3 * 0.75) + ($item->tot4)) / $item->num_expected :
                0;
            $sal_color = Osm2caiHelper::getSalColor($sal);

            $model = $childrenAbstractModel::find($item->id);

            $row = new Row(
                new Cell($item->full_code),
                new Cell($model->name),
                new Cell($item->tot1),
                new Cell($item->tot2),
                new Cell($item->tot3),
                new Cell($item->tot4),
                new Cell($tot),
                new Cell($item->num_expected ?? 0),
                new Cell('<div style="background-color: '.$sal_color.'; color: white; font-size: x-large">'.number_format($sal * 100, 2).' %</div>'),
                new Cell('<a href="/resources/'.($childrenTable == 'regions' ? 'region' : $childrenTable).'/'.$item->id.'">[VIEW]</a>'),
            );
            $data[] = $row;
        }

        $sectorsCard->data($data);

        return $sectorsCard;
    }

    private function _localCardsByModelClassName($user, $modelClassName)
    {
        $table = (new $modelClassName)->getTable();
        $models = $user->{$table}; // Get models from user relation

        $data = Cache::remember('local_cards_data_'.$user->id.'_'.$modelClassName, now()->addDays(2), function () use ($user, $modelClassName, $models, $table) {
            $numbers = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            foreach ($models as $model) {
                foreach ($model->hikingRoutes as $hr) {
                    $status = $hr->osm2cai_status;
                    $numbers[$status]++;
                }
            }

            $numProvinces = 0;
            $numAreas = 0;
            $numSectors = 0;
            $salHtml = '';

            if ($table == 'provinces') {
                $numProvinces = $user->provinces->count();
                foreach ($user->provinces as $province) {
                    $sal = $province->getSal();
                    $sal_color = Osm2caiHelper::getSalColor($sal);
                    $salHtml .= $province->name.'<div style="background-color: '.$sal_color.'; color: white; font-size: xx-large">'.
                        number_format($sal * 100, 2).' %</div>';

                    $numAreas += $province->areas->count();
                    if ($province->areas->count() > 0) {
                        foreach ($province->areas as $area) {
                            $numSectors += $area->sectors->count();
                        }
                    }
                }
            } elseif ($table == 'areas') {
                if ($user->areas->count() > 0) {
                    $numAreas = $user->areas->count();
                    foreach ($user->areas as $area) {
                        $sal = $area->getSal();
                        $sal_color = Osm2caiHelper::getSalColor($sal);
                        $salHtml .= $area->name.'<div style="background-color: '.$sal_color.'; color: white; font-size: xx-large">'.
                            number_format($sal * 100, 2).' %</div>';
                        $numSectors += $area->sectors->count();
                    }
                }
            } elseif ($table == 'sectors') {
                $numSectors = $user->sectors->count();
                foreach ($user->sectors as $sector) {
                    $sal = $sector->getSal();
                    $salColor = Osm2caiHelper::getSalColor($sal);
                    $salHtml .= $sector->name.'<div style="background-color: '.$salColor.'; color: white; font-size: xx-large">'.
                        number_format($sal * 100, 2).' %</div>';
                }
            }

            return [
                'numbers' => $numbers,
                'numProvinces' => $numProvinces,
                'numAreas' => $numAreas,
                'numSectors' => $numSectors,
                'salHtml' => $salHtml,
            ];
        });

        $tableSingular = Str::singular($table);
        ob_start();
        foreach ($models as $relatedModel) {
            $id = $relatedModel->id;
            ?>
            <h5><?= $relatedModel->name ?>: </h5>
            <a href="<?= route('loading-download', ['type' => 'geojson', 'model' => $tableSingular, 'id' => $id]) ?>">Download
                geojson
                Percorsi</a>
            <a href="<?= route('loading-download', ['type' => 'shapefile', 'model' => $tableSingular, 'id' => $id]) ?>">Download
                shape
                Percorsi</a>
            <a href="<?= route('loading-download', ['type' => 'csv', 'model' => $tableSingular, 'id' => $id]) ?>">Download
                csv
                Percorsi</a>
<?php
        }
        $downloadLinks = ob_get_clean();
        $syncDate = Cache::get('last_osm_sync') ?? 'N/A';

        $cards = [
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.username-card', [
                    'userName' => $user->name,
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.permessi-card', [
                    'roles' => $user->getRoleNames()->toArray(),
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.last-login-card', [
                    'lastLogin' => $user->last_login_at,
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->html($data['salHtml'])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->html('<div class="font-light">
                <p>&nbsp;</p>'.
                    $downloadLinks.
                    '<p>&nbsp;</p>
                 <p>Ultima sincronizzazione da osm: '.$syncDate.'</p>
                 </div>')
                ->center()
                ->width('full')
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.tot-model-card', [
                    'model' => 'province',
                    'num' => $data['numProvinces'],
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.tot-model-card', [
                    'model' => 'aree',
                    'num' => $data['numAreas'],
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.tot-model-card', [
                    'model' => 'settori',
                    'num' => $data['numSectors'],
                ])
                ->center()
                ->withBasicStyles(),
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.tot-model-card', [
                    'model' => 'percorsi',
                    'num' => array_sum($data['numbers']),
                ])
                ->center()
                ->withBasicStyles(),

            $this->_getSdaCard(1, $data['numbers'][1]),
            $this->_getSdaCard(2, $data['numbers'][2]),
            $this->_getSdaCard(3, $data['numbers'][3]),
            $this->_getSdaCard(4, $data['numbers'][4]),
        ];

        $cards = array_merge($cards, [$this->_getSectorsTableCardByModelClassName($user, $modelClassName)]);

        return $cards;
    }

    private function _getSdaCard(int $sda, int $num): HtmlCard
    {
        $path = '/resources/hiking-routes/lens/hiking-routes-status-'.$sda.'-lens';

        return (new HtmlCard())->width('1/4')
            ->html('<div style="background-color: '.Osm2caiHelper::getSdaColor($sda).'; color: white; font-size: xx-large">'.$num.'</div>')
            ->html('<div>#sda '.$sda.' <a href="'.url($path).'">[Esplora]</a></div>')
            ->center()
            ->withBasicStyles();
    }

    private function _getSectorsTableCardByModelClassName($user, $modelClassName): CustomTableCard
    {
        $sectorsCard = new CustomTableCard();

        $table = (new $modelClassName)->getTable();
        $modelNamesString = $user->$table->pluck('name')->implode(', ');

        $sectorsCard->title(__('SDA e SAL Settori - '.$modelNamesString));

        // Headings
        $sectorsCard->header([
            new Cell(__('Settore')),
            new Cell(__('Nome')),
            new Cell(__('#1')),
            new Cell(__('#2')),
            new Cell(__('#3')),
            new Cell(__('#4')),
            new Cell(__('#tot')),
            new Cell(__('#att')),
            new Cell(__('SAL')),
            new Cell(__('Actions')),
        ]);

        // Get sectors_id
        $sectorsIds = [];

        if ($table == 'provinces') {
            foreach ($user->provinces as $province) {
                if (Arr::accessible($province->areas)) {
                    foreach ($province->areas as $area) {
                        if (Arr::accessible($area->sectors)) {
                            $sectorsIds = array_merge($sectorsIds, $area->sectors->pluck('id')->toArray());
                        }
                    }
                }
            }
        } elseif ($table == 'areas') {
            foreach ($user->areas as $area) {
                if (Arr::accessible($area->sectors)) {
                    $sectorsIds = array_merge($sectorsIds, $area->sectors->pluck('id')->toArray());
                }
            }
        } elseif ($table == 'sectors') {
            $sectorsIds = $user->sectors->pluck('id')->toArray();
        }

        $items = DB::table('sectors')
            ->select([
                'sectors.id',
                'sectors.full_code',
                'sectors.num_expected',
                DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 1 THEN 1 END) as tot1'),
                DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 2 THEN 1 END) as tot2'),
                DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 3 THEN 1 END) as tot3'),
                DB::raw('COUNT(CASE WHEN hiking_routes.osm2cai_status = 4 THEN 1 END) as tot4'),
            ])
            ->leftJoin('hiking_route_sector', 'sectors.id', '=', 'hiking_route_sector.sector_id')
            ->leftJoin('hiking_routes', 'hiking_route_sector.hiking_route_id', '=', 'hiking_routes.id')
            ->whereIn('sectors.id', $sectorsIds)
            ->groupBy('sectors.id', 'sectors.full_code', 'sectors.num_expected')
            ->get();

        $data = [];
        foreach ($items as $item) {
            $tot = $item->tot1 + $item->tot2 + $item->tot3 + $item->tot4;
            $sal = (($item->tot1 * 0.25) + ($item->tot2 * 0.50) + ($item->tot3 * 0.75) + ($item->tot4)) / $item->num_expected;
            $sal_color = Osm2caiHelper::getSalColor($sal);
            $sector = Sector::find($item->id);

            $row = new Row(
                new Cell("{$item->full_code}"),
                new Cell($sector->human_name),
                new Cell($item->tot1),
                new Cell($item->tot2),
                new Cell($item->tot3),
                new Cell($item->tot4),
                new Cell($tot),
                new Cell($item->num_expected),
                new Cell('<div style="background-color: '.$sal_color.'; color: white; font-size: x-large">'.number_format($sal * 100, 2).' %</div>'),
                new Cell('<a href="/resources/sectors/'.$item->id.'">[VIEW]</a>'),
            );
            $data[] = $row;
        }

        $sectorsCard->data($data);

        return $sectorsCard;
    }

    /**
     * Get hiking routes filtered by status and user's territory
     *
     * @param int|array $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getHikingRoutes($status, $user = null)
    {
        $cacheKey = is_array($status) ? 'hikingRoutesSda'.implode('', $status) : 'hikingRoutesSda'.$status;

        //add user id to cache key to avoid conflicts between users
        if ($user) {
            $cacheKey .= '_user_'.$user->id;
        }

        return Cache::remember($cacheKey, 60 * 60 * 24 * 2, function () use ($status, $user) {
            $query = HikingRoute::select('issues_status');

            if (is_array($status)) {
                $query->whereIn('osm2cai_status', $status);
            } else {
                $query->where('osm2cai_status', $status);
            }

            if ($user) {
                $query->where(function ($q) use ($user) {
                    if ($user->region) {
                        $q->whereHas(
                            'regions',
                            fn ($query) => $query->where('regions.id', $user->region->id)
                        );
                    }

                    if ($user->areas->count()) {
                        $q->whereHas(
                            'areas',
                            fn ($query) => $query->whereIn('areas.id', $user->area->pluck('id'))
                        );
                    }

                    if ($user->provinces->count()) {
                        $q->whereHas(
                            'provinces',
                            fn ($query) => $query->whereIn('provinces.id', $user->provinces->pluck('id'))
                        );
                    }

                    if ($user->sectors->count()) {
                        $q->whereHas(
                            'sectors',
                            fn ($query) => $query->whereIn('sectors.id', $user->sectors->pluck('id'))
                        );
                    }
                });
            }

            return $query->get();
        });
    }
}
