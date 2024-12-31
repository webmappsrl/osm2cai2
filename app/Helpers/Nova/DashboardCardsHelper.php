<?php

namespace App\Helpers\Nova;

use App\Helpers\Osm2caiHelper;
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
                'sal' => number_format($sal * 100, 2),
                'backgroundColor' => Osm2caiHelper::getSalColor($sal),
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda3Sda4Card()
    {
        $tot = DB::table('hiking_routes')
            ->whereIn('osm2cai_status', [3, 4])
            ->whereNotNull('osmfeatures_data->properties->distance')
            ->selectRaw('COALESCE(SUM(NULLIF(REGEXP_REPLACE(TRIM(BOTH \'"\' FROM (osmfeatures_data->\'properties\'->\'distance\')::text), \'[^0-9.]+\', \'\', \'g\'), \'\')::float), 0) as total')
            ->first();

        $formatted = floatval($tot->total);

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => 'Totale km #sda3 e #sda4',
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda4Card()
    {
        $tot = DB::table('hiking_routes')
            ->where('osm2cai_status', 4)
            ->whereNotNull('osmfeatures_data->properties->distance')
            ->selectRaw('COALESCE(SUM(NULLIF(REGEXP_REPLACE(TRIM(BOTH \'"\' FROM (osmfeatures_data->\'properties\'->\'distance\')::text), \'[^0-9.]+\', \'\', \'g\'), \'\')::float), 0) as total')
            ->first();

        $formatted = floatval($tot->total);

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => 'Totale km #sda4',
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getNoPermissionsCard()
    {
        return (new HtmlCard())
            ->view('nova.cards.no-permissions-card')
            ->center()
            ->withBasicStyles();
    }
}
