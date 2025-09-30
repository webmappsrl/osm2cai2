<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Laravel\Nova\Dashboard;

class SectorsDashboard extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper;
    }

    public function label()
    {
        return __('Sectors Summary');
    }

    public function cards()
    {
        return $this->cardsService->getSectorsDashboardCards();
    }

    public function uriKey()
    {
        return 'settori';
    }
}
