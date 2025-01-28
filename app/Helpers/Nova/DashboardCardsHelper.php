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
        $sal = cache()->remember('national_sal', now()->addDays(2), function () {
            return (HikingRoute::where('osm2cai_status', 1)->count() * 0.25 +
                HikingRoute::where('osm2cai_status', 2)->count() * 0.50 +
                HikingRoute::where('osm2cai_status', 3)->count() * 0.75 +
                HikingRoute::where('osm2cai_status', 4)->count()
            ) / Region::sum('num_expected');
        });

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

        $total = cache()->remember($cacheKey, now()->addDays(2), function () use ($status) {
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

    public function getItalyDashboardCards()
    {
        $numbers = cache()->remember('italy_dashboard_data', now()->addDays(2), function () {
            $values = DB::table('hiking_routes')
                ->select('osm2cai_status', DB::raw('count(*) as num'))
                ->groupBy('osm2cai_status')
                ->get();

            $numbers = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach ($values as $value) {
                $numbers[$value->osm2cai_status] = $value->num;
            }

            return $numbers;
        });

        $tot = array_sum($numbers);

        return [
            'italy-total' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-total', ['tot' => $tot])
                ->center()
                ->withBasicStyles(),
            'sda1' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[1],
                    'sda' => 1,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(1),
                ])
                ->center()
                ->withBasicStyles(),

            'sda2' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[2],
                    'sda' => 2,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(2),
                ])
                ->center()
                ->withBasicStyles(),

            'sda3' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[3],
                    'sda' => 3,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(3),
                ])
                ->center()
                ->withBasicStyles(),

            'sda4' => (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[4],
                    'sda' => 4,
                    'backgroundColor' => Osm2caiHelper::getSdaColor(4),
                ])
                ->center()
                ->withBasicStyles(),
        ];
    }
}
