<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Helpers\Osm2caiHelper;
use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Mako\CustomTableCard\CustomTableCard;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\Table\Row;

class Main extends Dashboard
{
    protected $dashboardCardsHelper;

    public function __construct()
    {
        $this->dashboardCardsHelper = new DashboardCardsHelper();
    }

    public function name()
    {
        return __('Dashboard');
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $cards = [];

        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        switch ($roles) {
            case in_array('Administrator', $roles):
                $cards = $this->nationalCards($user);
                break;
            case in_array('National Referent', $roles):
                $cards = $this->nationalCards($user);
                break;
            case in_array('Regional Referent', $roles):
                $cards = $this->regionalCards($user);
                break;
            case in_array('Local Referent', $roles):
                if ($user->sectors->count()) {
                    $cards = $this->_localCardsByModelClassName(Sector::class);
                } elseif ($user->areas->count()) {
                    $cards = $this->_localCardsByModelClassName(Area::class);
                } else {
                    $cards = $this->_localCardsByModelClassName(Province::class);
                }
                break;
            default:
                $cards = [$this->dashboardCardsHelper->getNoPermissionsCard()];
                break;
        }

        return $cards;
    }

    private function nationalCards($user)
    {
        $values = DB::table('hiking_routes')
            ->select('osm2cai_status', DB::raw('count(*) as num'))
            ->groupBy('osm2cai_status')
            ->get();

        $roles = $user->getRoleNames()->toArray();

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

        $cards = [
            (new HtmlCard())->width('1/4')->view('nova.cards.username-card', ['userName' => $user->name])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.permessi-card', ['roles' => $roles])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.last-login-card', ['lastLogin' => $user->last_login_at])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sal-nazionale', ['sal' => number_format($sal * 100, 2, '.', ''), 'backgroundColor' => Osm2caiHelper::getSalColor($sal)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[1], 'sda' => 1, 'backgroundColor' => Osm2caiHelper::getSdaColor(1)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[2], 'sda' => 2, 'backgroundColor' => Osm2caiHelper::getSdaColor(2)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[3], 'sda' => 3, 'backgroundColor' => Osm2caiHelper::getSdaColor(3)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[4], 'sda' => 4, 'backgroundColor' => Osm2caiHelper::getSdaColor(4)])->center(true)->withBasicStyles(),
        ];

        $cards[] = $this->regionsTableCard();

        return $cards;
    }

    private function regionalCards($user)
    {
        $region = $user->region;

        $numbers = [];
        $numbers[1] = 0;
        $numbers[2] = 0;
        $numbers[3] = 0;
        $numbers[4] = 0;
        $hikingRoutes = $region->intersectings['hiking_routes'] ?? [];

        foreach ($hikingRoutes as $id => $updated_at) {
            $hrStatus = HikingRoute::find($id)->osm2cai_status;
            $numbers[$hrStatus]++;
        }

        $num_areas = 0;
        $area_codes = [];
        $num_sectors = 0;
        foreach ($region->provinces as $province) {
            array_push($area_codes, implode(',', $province->areas->pluck('code')->toArray()));
            if ($province->areas->count() > 0) {
                foreach ($province->areas as $area) {
                    $num_sectors += $area->sectors->count();
                }
            }
        }
        $area_codes = implode(',', $area_codes);
        $area_codes = implode(',', array_unique(explode(',', $area_codes)));
        $num_areas = count(explode(',', $area_codes));

        $sal = $region->getSal();
        $sal_color = Osm2caiHelper::getSalColor($sal);

        $SALIssueStatus = $this->getSalIssueStatus($region);

        $cards = [
            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.username-regional-card', ['userName' => $user->name])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.permissions-regional-card', ['permissions' => $user->getRoleNames()->toArray()])
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
                    'sal' => number_format($sal * 100, 2),
                    'backgroundColor' => $sal_color,
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
                    'value' => $num_areas,
                    'label' => '#aree',
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => $num_sectors,
                    'label' => '#settori',
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())
                ->width('1/4')
                ->view('nova.cards.stats-card', [
                    'value' => array_sum($numbers),
                    'label' => '#tot percorsi',
                ])
                ->center()
                ->withBasicStyles(),

            // SDA cards
            $this->getSdaRegionalCard(1, $numbers[1]),
            $this->getSdaRegionalCard(2, $numbers[2]),
            $this->getSdaRegionalCard(3, $numbers[3]),
            $this->getSdaRegionalCard(4, $numbers[4]),
        ];

        $provinceCards = [];
        foreach ($region->provinces as $province) {
            $provinceCards[] = $this->getChildrenTableCardByModel($province); //areas
        }

        //$cardsService = new CardsService;
        $cards = array_merge(
            $cards,
            [$this->getChildrenTableCardByModel($region)], //provinces
            $provinceCards, //areas
            //[$cardsService->getSectorsTableCard()]//sectors
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

        // Fetch regions data
        $regions = Region::all();

        $data = [];
        foreach ($regions as $region) {
            $hikingRoutes = $region->intersectings['hiking_routes'] ?? [];
            $att = $region->num_expected ?? 0;

            $tot1 = 0;
            $tot2 = 0;
            $tot3 = 0;
            $tot4 = 0;

            if (is_array($hikingRoutes)) {
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

            // Calcolo del SAL
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
                new Cell($region->name ?? 'Sconosciuto'),
                new Cell((string) $tot1),
                new Cell((string) $tot2),
                new Cell((string) $tot3),
                new Cell((string) $tot4),
                new Cell((string) $tot),
                new Cell((string) $att),
                new Cell('<div style="background-color: '.$sal_color.'; color: white; font-size: x-large">'.$salDisplay.'</div>'),
            );
            $data[] = $row;
        }

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

        // Get stats using the new trait method
        $items = $childrenAbstractModel::getStatsForIds($childrenIds);

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
}
