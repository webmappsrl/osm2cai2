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

    private function getTotalKmCard($status, $label)
    {
        $cacheKey = is_array($status) ? 'total_km_' . implode('_', $status) : 'total_km_' . $status;

        $total = cache()->remember($cacheKey, now()->addDay(), function () use ($status) {
            $query = DB::table('hiking_routes')
                ->selectRaw('
                    COALESCE(
                        SUM(ST_Length(geometry::geography) / 1000), 
                        0
                    ) as total
                ');

            if (is_array($status)) {
                $query->whereIn('osm2cai_status', $status);
            } else {
                $query->where('osm2cai_status', $status);
            }

            $tot = $query->first();
            return round(floatval($tot->total), 2);
        });

        $formatted = number_format($total, 2, ',', '.');

        return (new HtmlCard())
            ->width('1/4')
            ->view('nova.cards.total-km', [
                'total' => $formatted,
                'label' => $label,
            ])
            ->center()
            ->withBasicStyles();
    }

    public function getTotalKmSda3Sda4Card()
    {
        return $this->getTotalKmCard([3, 4], 'Totale km #sda3 e #sda4');
    }

    public function getTotalKmSda4Card()
    {
        return $this->getTotalKmCard(4, 'Totale km #sda4');
    }

    public function getNoPermissionsCard()
    {
        return (new HtmlCard())
            ->view('nova.cards.no-permissions-card')
            ->center()
            ->withBasicStyles();
    }
}
