<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Helpers\Osm2caiHelper;
use App\Nova\Metrics\TotalAreasCount;
// use App\Services\CardsService;
use App\Nova\Metrics\TotalProvincesCount;
use App\Nova\Metrics\TotalRegionsCount;
use App\Nova\Metrics\TotalSectorsCount;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ItalyDashboard extends Dashboard
{

    private $cardsHelper;

    public function __construct()
    {
        $this->cardsHelper = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Riepilogo nazionale';
    }


    public function cards()
    {
        $italyDashboardCards = $this->cardsHelper->getItalyDashboardCards();

        return [
            (new TotalProvincesCount())->width('1/4'),
            (new TotalAreasCount())->width('1/4'),
            (new TotalSectorsCount())->width('1/4'),
            $italyDashboardCards['italy-total'],
            $italyDashboardCards['sda1'],
            $italyDashboardCards['sda2'],
            $italyDashboardCards['sda3'],
            $italyDashboardCards['sda4'],
            $this->cardsHelper->getNationalSalCard(),
            $this->cardsHelper->getTotalKmSda3Sda4Card(),
            $this->cardsHelper->getTotalKmSda4Card(),
        ];
    }

    public function uriKey()
    {
        return 'italy-dashboard';
    }
}
