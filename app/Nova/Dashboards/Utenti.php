<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Nova\Metrics\IssueLastUpdatePerMonth;
use App\Nova\Metrics\ValidatedHrPerMonth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class Utenti extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Riepilogo utenti';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsService->getUtentiDashboardCards();
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'utenti';
    }
}
