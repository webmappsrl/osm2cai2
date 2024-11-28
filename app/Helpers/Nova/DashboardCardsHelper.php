<?php

namespace App\Helpers\Nova;

use App\Helpers\Osm2CaiHelper;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class DashboardCardsHelper
{
    public function getNationalSalCard()
    {
        $sal = (HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
            HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
            HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
            HikingRoute::where('osm2cai_status', 4)->count()
        ) / Region::sum('num_expected');

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.sal-nazionale', [
                'sal' => $sal,
                'backgroundColor' => Osm2CaiHelper::getSalColor($sal)
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda3Sda4Card()
    {
        $tot = DB::table('hiking_routes')
            ->whereIn('osm2cai_status', [3, 4])
            ->selectRaw('SUM((tdh->\'distance\')::float) as total')
            ->first();

        $formatted = floatval($tot->total);

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => 'Totale km #sda3 e #sda4'
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda4Card()
    {
        $tot = DB::table('hiking_routes')
            ->where('osm2cai_status', 4)
            ->selectRaw('SUM((tdh->\'distance\')::float) as total')
            ->first();

        $formatted = floatval($tot->total);

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => 'Totale km #sda4'
            ])
            ->center()
            ->withBasicStyles();
    }
}
