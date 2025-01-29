<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use App\Helpers\Osm2caiHelper;
use App\Models\Sector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

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
