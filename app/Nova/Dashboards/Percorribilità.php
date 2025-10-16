<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Models\User;
use Laravel\Nova\Dashboard;

class Percorribilità extends Dashboard
{
    private $cardsService;

    public function __construct(?User $user = null)
    {
        $this->cardsService = new DashboardCardsHelper;
    }

    public function label()
    {
        return __('Riepilogo Percorribilità');
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsService->getPercorribilitàDashboardCards(auth()->user());
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'percorribilità';
    }
}
