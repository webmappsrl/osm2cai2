<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Card;
use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\DB;
use Ericlagarda\NovaTextCard\TextCard;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class SALMiturAbruzzo extends Dashboard
{

    public function label()
    {
        return 'Riepilogo MITUR-Abruzzo';
    }
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $regions = DB::table('regions')->get();

        $sumMountainGroups = DB::select('SELECT count(*) as count FROM mountain_groups')[0]->count;
        $sumEcPois = DB::select('SELECT count(*) as count FROM ec_pois')[0]->count;
        $sumHikingRoutes = DB::select('SELECT count(*) as count FROM hiking_routes WHERE osm2cai_status = 4')[0]->count;
        $sumPoiTotal = $sumEcPois + $sumHikingRoutes;
        $sumCaiHuts = DB::select('SELECT count(*) as count FROM cai_huts')[0]->count;
        $sumClubs = DB::select('SELECT count(*) as count FROM clubs')[0]->count;

        $totalsGlobal = [
            'sumMountainGroups' => $sumMountainGroups,
            'sumEcPois' => $sumEcPois,
            'sumHikingRoutes' => $sumHikingRoutes,
            'sumPoiTotal' => $sumPoiTotal,
            'sumCaiHuts' => $sumCaiHuts,
            'sumClubs' => $sumClubs,
        ];
        return [
            (new HtmlCard())
                ->width('full')
                ->view('nova.cards.sal-regions-table', [
                    'regions' => $regions,
                    'totals' => $totalsGlobal,
                ])
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
        return 'sal-mitur-abruzzo';
    }
}
