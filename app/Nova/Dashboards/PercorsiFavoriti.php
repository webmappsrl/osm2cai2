<?php

namespace App\Nova\Dashboards;

use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class PercorsiFavoriti extends Dashboard
{
    public function label()
    {
        return 'Percorsi Favoriti';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $regions = DB::table('regions')
            ->select([
                'regions.name as region_name',
                DB::raw('(SELECT COUNT(*) FROM hiking_route_region hrr JOIN hiking_routes hr ON hrr.hiking_route_id = hr.id WHERE hrr.region_id = regions.id AND hr.region_favorite = true) as favorite_routes_count'),
                DB::raw('(SELECT COUNT(*) FROM hiking_route_region hrr JOIN hiking_routes hr ON hrr.hiking_route_id = hr.id WHERE hrr.region_id = regions.id AND hr.osm2cai_status = 4) as sda4_routes_count'),
            ])
            ->orderByDesc('favorite_routes_count')
            ->get();

        return [
            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.percorsi-favoriti-table', ['regions' => $regions])
                ->withBasicStyles(),
        ];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'percorsi-favoriti';
    }
}
