<?php

namespace App\Helpers;

use App\Helpers\Osm2CaiHelper;
use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\Sector;
use Ericlagarda\NovaTextCard\TextCard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mako\CustomTableCard\CustomTableCard;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\Table\Row;

class DashboardCardsHelper
{
    public function getNationalSalCard()
    {
        $sal = (HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
            HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
            HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
            HikingRoute::where('osm2cai_status', 4)->count()
        ) / Region::sum('num_expected');
        $sal_color = Osm2CaiHelper::getSalColor($sal);

        return (new TextCard())
            ->width('1/4')
            ->heading('<div style="background-color: '.$sal_color.'; color: white; font-size: xx-large">'.number_format($sal * 100, 2).' %</div>')
            ->headingAsHtml()
            ->text('SAL Nazionale');
    }

    /**
     * @return CustomTableCard
     */
    public function getSectorsTableCard(): CustomTableCard
    {
        $sectorsCard = new CustomTableCard();
        $sectorsCard->title(__('SDA e SAL Settori - '.Auth::user()->region->name));

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
        $sectors_id = [];
        foreach (Auth::user()->region->provinces as $province) {
            if (Arr::accessible($province->areas)) {
                foreach ($province->areas as $area) {
                    if (Arr::accessible($area->sectors)) {
                        $sectors_id = array_merge($sectors_id, $area->sectors->pluck('id')->toArray());
                    }
                }
            }
        }

        // Extract data from views
        // select name,code,tot1,tot2,tot3,tot4,num_expected from regions_view;
        $items = DB::table('sectors_view')
            ->select('id', 'full_code', 'tot1', 'tot2', 'tot3', 'tot4', 'num_expected')
            ->whereIn('id', $sectors_id)
            ->get();

        $data = [];
        foreach ($items as $item) {
            $tot = $item->tot1 + $item->tot2 + $item->tot3 + $item->tot4;
            if ($item->num_expected == 0) {
                $sal = 0;
            } else {
                $sal = (($item->tot1 * 0.25) + ($item->tot2 * 0.50) + ($item->tot3 * 0.75) + ($item->tot4)) / $item->num_expected;
            }
            $sal_color = Osm2CaiHelper::getSalColor($sal);
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

    public function getTotalKmSda3Sda4Card()
    {
        $tot = DB::table('regions_view')->selectRaw('SUM(km_tot3) + SUM (km_tot4) as total')
            ->get();

        $formatted = floatval($tot->first()->total);

        return (new TextCard())
            ->width('1/4')
            ->text('Totale km #sda3 e #sda4')
            ->heading('<div style="font-size: xx-large">'.$formatted.'</div>')
            ->headingAsHtml();
    }

    public function getTotalKmSda4Card()
    {
        $tot = DB::table('regions_view')->selectRaw('SUM (km_tot4) as total')
            ->get();

        $formatted = floatval($tot->first()->total);

        return (new TextCard())
            ->width('1/4')
            ->text('Totale km #sda4')
            ->heading('<div style="font-size: xx-large">'.$formatted.'</div>')
            ->headingAsHtml();
    }
}
