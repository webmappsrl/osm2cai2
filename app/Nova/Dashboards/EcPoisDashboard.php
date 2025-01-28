<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use App\Nova\Metrics\EcPoisTrend;
use App\Nova\Metrics\EcPoisTypePartition;
use App\Helpers\Nova\DashboardCardsHelper;
use App\Nova\Metrics\EcPoisScorePartition;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class EcPoisDashboard extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'POIS';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [
            new EcPoisTrend,
            $this->cardsService->getEcPoisDashboardCard(),
            new EcPoisScorePartition,
            new EcPoisTypePartition,
        ];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'ec-pois';
    }
}
