<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Nova\Metrics\ValidatedHrPerMonth;
use App\Helpers\Nova\DashboardCardsHelper;
use App\Nova\Metrics\IssueLastUpdatePerMonth;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

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
