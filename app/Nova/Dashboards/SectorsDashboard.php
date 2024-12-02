<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use App\Models\Sector;
use App\Helpers\Osm2CaiHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class SectorsDashboard extends Dashboard
{
    public function label()
    {
        return 'Riepilogo settori';
    }

    public function cards()
    {
        // Get sectors_id
        $sectors_id = [];
        foreach (auth()->user()->region->provinces as $province) {
            if (Arr::accessible($province->areas)) {
                foreach ($province->areas as $area) {
                    if (Arr::accessible($area->sectors)) {
                        $sectors_id = array_merge($sectors_id, $area->sectors->pluck('id')->toArray());
                    }
                }
            }
        }

        // Query to get sectors with their hiking route counts by osm2cai_status
        // Selects:
        // - sector id, full_code and num_expected
        // - count of hiking routes with osm2cai_status = 1 as tot1
        // - count of hiking routes with osm2cai_status = 2 as tot2 
        // - count of hiking routes with osm2cai_status = 3 as tot3
        // - count of hiking routes with osm2cai_status = 4 as tot4
        // Left joins with hiking_route_sector and hiking_routes tables
        // Filters by the provided sector IDs
        // Groups by sector fields to get counts per sector
        $items = DB::table('sectors')
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
            ->whereIn('sectors.id', $sectors_id)
            ->groupBy('sectors.id', 'sectors.full_code', 'sectors.num_expected')
            ->get();

        $sectors = $items->map(function ($item) {
            $sector = Sector::find($item->id);
            $tot = $item->tot1 + $item->tot2 + $item->tot3 + $item->tot4;
            $sal = $item->num_expected == 0 ? 0 : (($item->tot1 * 0.25) + ($item->tot2 * 0.50) + ($item->tot3 * 0.75) + ($item->tot4)) / $item->num_expected;

            return (object)[
                'id' => $item->id,
                'full_code' => $item->full_code,
                'human_name' => $sector->human_name,
                'tot1' => $item->tot1,
                'tot2' => $item->tot2,
                'tot3' => $item->tot3,
                'tot4' => $item->tot4,
                'num_expected' => $item->num_expected,
                'sal' => $sal,
                'sal_color' => Osm2CaiHelper::getSalColor($sal)
            ];
        });

        return [
            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.sectors-table', ['sectors' => $sectors])
                ->withBasicStyles(),
        ];
    }

    public function uriKey()
    {
        return 'settori';
    }
}
