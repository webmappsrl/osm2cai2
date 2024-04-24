<?php

namespace App\Providers;

use App\Nova\CaiHut;
use DB;
use App\Nova\User;
use Laravel\Nova\Nova;
use Laravel\Nova\Badge;
use App\Nova\NaturalSpring;
use App\Nova\MountainGroups;
use Illuminate\Http\Request;
use App\Nova\Dashboards\Main;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
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
                ]),
                MenuSection::make('Tools', [
                    MenuItem::externalLink('Display Jobs', url('/jobs'))->withBadgeIf(Badge::make('Some jobs failed', 'warning'), 'warning', fn () => DB::table('queue_monitor')->where('status', 2)->count() > 0)->openInNewTab(),

                ])->icon('briefcase'),
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
