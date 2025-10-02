<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Laravel\Nova\Dashboard;

class SALMiturAbruzzo extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper;
    }

    public function label()
    {
        return __('Riepilogo MITUR-Abruzzo');
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsService->getSALMiturAbruzzoDashboardCards();
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
