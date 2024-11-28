<?php

namespace App\Nova\Dashboards;

use App\Helpers\Osm2caiHelper;
use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Mako\CustomTableCard\CustomTableCard;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\Table\Row;

class Main extends Dashboard
{
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
        $user = Auth::user();
        $userName = $user->name;
        $roles = $user->getRoleNames();

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

        $cards = [
            (new HtmlCard())->width('1/4')->view('nova.cards.username-card', ['userName' => $userName])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.permessi-card', ['roles' => $roles->toArray()])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.last-login-card', ['lastLogin' => $user->last_login_at])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sal-nazionale', ['sal' => number_format($sal * 100, 2, '.', ''), 'backgroundColor' => Osm2CaiHelper::getSalColor($sal)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[1], 'sda' => 1, 'backgroundColor' => Osm2caiHelper::getSdaColor(1)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[2], 'sda' => 2, 'backgroundColor' => Osm2caiHelper::getSdaColor(2)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[3], 'sda' => 3, 'backgroundColor' => Osm2caiHelper::getSdaColor(3)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda', ['num' => $numbers[4], 'sda' => 4, 'backgroundColor' => Osm2caiHelper::getSdaColor(4)])->center(true)->withBasicStyles(),
        ];

        $cards[] = $this->_getRegionsTableCard();

        return $cards;
    }

    /**
     * @return CustomTableCard
     */
    private function _getRegionsTableCard(): CustomTableCard
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
            $hikingRoutes = $region->hiking_routes_intersecting ?? [];
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
                $sal = min($sal, 1); // Assicura che SAL non superi il 100%
                $salDisplay = number_format($sal * 100, 2) . ' %';
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
                new Cell('<div style="background-color: ' . $sal_color . '; color: white; font-size: x-large">' . $salDisplay . '</div>'),
            );
            $data[] = $row;
        }

        $regionsCard->data($data);

        return $regionsCard;
    }
}
