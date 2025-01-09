<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\IssueLastUpdatePerMonth;
use App\Nova\Metrics\ValidatedHrPerMonth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class Utenti extends Dashboard
{
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
        $usersByRole = Cache::remember('usersByRole', 60, function () {
            return
                DB::table('users')
                ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name', DB::raw('count(*) as count'))
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'name')
                ->toArray();
        });

        $usersByRegion = Cache::remember('usersByRegion', 60, function () {
            return
                [
                    'Region' => DB::table('users')->whereNotNull('region_id')->count(),
                    'Province' => DB::table('province_user')->distinct('user_id')->count('user_id'),
                    'Area' => DB::table('area_user')->distinct('user_id')->count('user_id'),
                    'Sector' => DB::table('sector_user')->distinct('user_id')->count('user_id')
                ];
        });

        $mostActiveUsers = Cache::remember('mostActiveUsers', 60, function () {
            return DB::select("
                SELECT u.id AS user_id, u.name AS user_name, COUNT(DISTINCT hr.id) AS numero_validazioni
                FROM users u
                JOIN hiking_routes hr ON u.id = hr.validator_id
                WHERE hr.osm2cai_status = '4'
                GROUP BY u.id, u.name
                ORDER BY numero_validazioni DESC
                LIMIT 5
            ");
        });

        return [
            new \App\Nova\Metrics\TotalUsers,
            new \App\Nova\Metrics\UserDistributionByRole($usersByRole),
            new \App\Nova\Metrics\UserDistributionByRegion($usersByRegion),
            (new HtmlCard())
                ->width('1/2')
                ->view('nova.cards.most-active-users', ['users' => $mostActiveUsers])
                ->withBasicStyles(),
            (new ValidatedHrPerMonth()),
            (new IssueLastUpdatePerMonth()),
        ];
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
