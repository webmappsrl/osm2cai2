<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
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

        if ($user->hasRole(UserRole::Administrator)) {
            return __('Main Dashboard');
        }

        if ($user->hasRole(UserRole::NationalReferent)) {
            return __('National Dashboard');
        }

        if ($user->hasRole(UserRole::RegionalReferent)) {
            return __('Regional Dashboard');
        }

        if ($user->hasRole(UserRole::LocalReferent)) {
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
