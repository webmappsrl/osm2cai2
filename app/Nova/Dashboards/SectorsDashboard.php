<?php

namespace App\Nova\Dashboards;

use App\Models\Sector;
use Illuminate\Support\Arr;
use Laravel\Nova\Dashboard;
use App\Helpers\Osm2caiHelper;
use Illuminate\Support\Facades\DB;
use App\Helpers\Nova\DashboardCardsHelper;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class SectorsDashboard extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Riepilogo settori';
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
