<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Laravel\Nova\Dashboard;
use App\Helpers\Osm2CaiHelper;
// use App\Services\CardsService;
use Illuminate\Support\Facades\DB;
use App\Nova\Metrics\TotalAreasCount;
use App\Nova\Metrics\TotalRegionsCount;
use App\Nova\Metrics\TotalSectorsCount;
use App\Nova\Metrics\TotalProvincesCount;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class ItalyDashboard extends Dashboard
{
    public function label()
    {
        return 'Riepilogo nazionale';
    }

    public function cards()
    {
        $values = DB::table('hiking_routes')
            ->select('osm2cai_status', DB::raw('count(*) as num'))
            ->groupBy('osm2cai_status')
            ->get();

        $numbers = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($values as $value) {
            $numbers[$value->osm2cai_status] = $value->num;
        }

        $tot = array_sum($numbers) - ($numbers[0] ?? 0);
        $cardsService = new DashboardCardsHelper;

        return [
            (new TotalProvincesCount())->width('1/4'),
            (new TotalAreasCount())->width('1/4'),
            (new TotalSectorsCount())->width('1/4'),
            (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-total', ['tot' => $tot])
                ->center()
                ->withBasicStyles(),

            // SDA Cards
            (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[1],
                    'sda' => 1,
                    'backgroundColor' => Osm2CaiHelper::getSdaColor(1)
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[2],
                    'sda' => 2,
                    'backgroundColor' => Osm2CaiHelper::getSdaColor(2)
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[3],
                    'sda' => 3,
                    'backgroundColor' => Osm2CaiHelper::getSdaColor(3)
                ])
                ->center()
                ->withBasicStyles(),

            (new HtmlCard())->width('1/4')
                ->view('nova.cards.italy-sda', [
                    'number' => $numbers[4],
                    'sda' => 4,
                    'backgroundColor' => Osm2CaiHelper::getSdaColor(4)
                ])
                ->center()
                ->withBasicStyles(),

            $cardsService->getNationalSalCard(),
            $cardsService->getTotalKmSda3Sda4Card(),
            $cardsService->getTotalKmSda4Card()
        ];
    }

    public function uriKey()
    {
        return 'italy-dashboard';
    }
}
