<?php

namespace App\Nova\Dashboards;

use App\Models\Region;
use App\Models\Sector;
use App\Models\HikingRoute;
use Laravel\Nova\Cards\Help;
use App\Helpers\Osm2caiHelper;
use Illuminate\Support\Facades\DB;
use Mako\CustomTableCard\Table\Row;
use Illuminate\Support\Facades\Auth;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\CustomTableCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

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

        $sal = (HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
            HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
            HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
            HikingRoute::where('osm2cai_status', 4)->count()
        ) / Sector::sum('num_expected');


        $cards = [
            (new HtmlCard())->width('1/4')->view('nova.cards.username-card', ['userName' => $userName])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.permessi-card', ['roles' => $roles->toArray()])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.last-login-card', ['lastLogin' => $user->last_login_at])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sal-nazionale', ['sal' => number_format($sal * 100, 2) . ' %'])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda-1', ['backgroundColor' => Osm2caiHelper::getSdaColor(1)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda-2', ['backgroundColor' => Osm2caiHelper::getSdaColor(2)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda-3', ['backgroundColor' => Osm2caiHelper::getSdaColor(3)])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sda-4', ['backgroundColor' => Osm2caiHelper::getSdaColor(4)])->center(true)->withBasicStyles(),
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

        // Extract data from views
        // select name,code,tot1,tot2,tot3,tot4,num_expected from regions_view;
        $items = DB::table('regions')
            ->select('name')
            ->get();

        $data = [];
        foreach ($items as $item) {
            $tot = 'x';
            $sal = 0;
            $sal_color = Osm2caiHelper::getSalColor($sal);

            $row = new Row(
                new Cell("{$item->name}"),
                new Cell($tot),
                new Cell($tot),
                new Cell($tot),
                new Cell($tot),
                new Cell($tot),
                new Cell($tot),
                new Cell('<div style="background-color: ' . $sal_color . '; color: white; font-size: x-large">' . number_format($sal * 100, 2) . ' %</div>'),
            );
            $data[] = $row;
        }

        $regionsCard->data($data);

        return $regionsCard;
    }
}
