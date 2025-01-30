<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Helpers\Osm2caiHelper;
use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Mako\CustomTableCard\CustomTableCard;
use Mako\CustomTableCard\Table\Cell;
use Mako\CustomTableCard\Table\Row;

class Main extends Dashboard
{
    private $cardsHelper;

    public function __construct()
    {
        $this->cardsHelper = new DashboardCardsHelper();
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
