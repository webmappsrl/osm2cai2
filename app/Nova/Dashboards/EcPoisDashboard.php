<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Nova\Metrics\EcPoisScorePartition;
use App\Nova\Metrics\EcPoisTrend;
use App\Nova\Metrics\EcPoisTypePartition;
use Laravel\Nova\Dashboard;

class EcPoisDashboard extends Dashboard
{
    private $cardsHelper;

    public function __construct()
    {
        $this->cardsHelper = new DashboardCardsHelper;
    }

    public function label()
    {
        return __('POIS');
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
            $this->cardsHelper->getEcPoisDashboardCard(),
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
