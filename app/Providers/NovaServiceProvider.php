<?php

namespace App\Providers;

use App\Nova\Area;
use App\Nova\CaiHut;
use App\Nova\Club;
use App\Nova\Dashboards\Main;
use App\Nova\EcPoi;
use App\Nova\HikingRoute;
use App\Nova\MountainGroups;
use App\Nova\Municipality;
use App\Nova\NaturalSpring;
use App\Nova\Poles;
use App\Nova\Province;
use App\Nova\Region;
use App\Nova\Sector;
use App\Nova\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Badge;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->getFooter();

        Nova::mainMenu(function (Request $request) {
            return [
                MenuSection::dashboard(Main::class)->icon('chart-bar'),

                MenuSection::make('Resources', [
                    MenuItem::resource(User::class),
                    MenuItem::resource(MountainGroups::class),
                    MenuItem::resource(NaturalSpring::class),
                    MenuItem::resource(CaiHut::class),
                    MenuItem::resource(Club::class),
                    MenuItem::resource(Sector::class),
                    MenuItem::resource(Area::class),
                    MenuItem::resource(Municipality::class),
                    MenuItem::resource(Province::class),
                    MenuItem::resource(Region::class),
                    MenuItem::resource(EcPoi::class),
                    MenuItem::resource(Poles::class),
                    MenuItem::resource(HikingRoute::class),
                ]),
                MenuSection::make('Tools', [
                    MenuItem::externalLink('Horizon', url('/horizon'))->openInNewTab(),
                    MenuItem::externalLink('logs', url('logs'))->openInNewTab()

                ])->icon('briefcase')->canSee(function (Request $request) {
                    return $request->user()->email === 'team@webmapp.it';
                })
            ];
        });
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            return in_array($user->email, [
                'team@webmapp.it',
            ]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array
     */
    protected function dashboards()
    {
        return [
            new Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [
            //
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    //create a footer
    private function getFooter()
    {
        Nova::footer(function () {
            return Blade::render('nova/footer');
        });
    }
}
