<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    private $cardsHelper;

    public function __construct()
    {
        $this->cardsHelper = new DashboardCardsHelper;
    }

    public function name()
    {
        $user = Auth::user();

        if ($user->hasRole('Administrator')) {
            return __('Main Dashboard');
        }

        if ($user->hasRole('National Referent')) {
            return __('National Dashboard');
        }

        if ($user->hasRole('Regional Referent')) {
            return __('Regional Dashboard');
        }

        if ($user->hasRole('Local Referent')) {
            return __('Local Dashboard');
        }

        return __('Main Dashboard');
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsHelper->getMainDashboardCards();
    }
}
