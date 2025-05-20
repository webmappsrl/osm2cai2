<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Laravel\Nova\Dashboard;

class AcquaSorgente extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper;
    }

    public function label()
    {
        return 'Riepilogo Acqua Sorgente';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsService->getAcquaSorgenteDashboardCards();
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'acqua-sorgente';
    }
}
