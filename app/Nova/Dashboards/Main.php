<?php

namespace App\Nova\Dashboards;

use App\Helpers\Osm2caiHelper;
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
        $cards = [
            (new HtmlCard())->width('1/4')->view('nova.cards.username-card', ['userName' => Auth::user()->name])->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.permessi-card')->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.last-login-card')->center(true)->withBasicStyles(),
            (new HtmlCard())->width('1/4')->view('nova.cards.sal-nazionale')->center(true)->withBasicStyles(),
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
                new Cell('<div style="background-color: '.$sal_color.'; color: white; font-size: x-large">'.number_format($sal * 100, 2).' %</div>'),
            );
            $data[] = $row;
        }

        $regionsCard->data($data);

        return $regionsCard;
    }
}
