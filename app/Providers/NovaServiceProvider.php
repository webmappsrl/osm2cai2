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
                // Dashboard
                MenuSection::make('Dashboard', [
                    MenuItem::link('Riepilogo nazionale', '/dashboards/main'),
                    MenuItem::link('Percorsi Favoriti', '/dashboards/main'),
                    MenuItem::link('POIS', '/dashboards/main'),
                    MenuItem::link('Riepilogo utenti', '/dashboards/main'),
                    MenuItem::link('Riepilogo Percorribilità', '/dashboards/main'),
                    MenuItem::link('Riepilogo MITUR-Abruzzo', '/dashboards/main'),
                    MenuItem::link('Riepilogo Acqua Sorgente', '/dashboards/main'),
                ])->icon('chart-bar')->collapsable(),

                // Rete Escursionistica
                MenuSection::make('Rete Escursionistica', [
                    MenuSection::make('Sentieri', [
                        MenuItem::resource(HikingRoute::class, 'Percorsi'),
                        MenuItem::link('Itinerari', '/dashboards/main'),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Luoghi3', [
                        MenuItem::resource(Poles::class, 'Poles'),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Unità Territoriali', [
                        MenuItem::resource(Sector::class, 'Sezioni'),
                        MenuItem::resource(Municipality::class, 'Comuni'),
                        MenuItem::resource(Sector::class, 'Settori'),
                        MenuItem::resource(Area::class, 'Aree'),
                        MenuItem::resource(Province::class, 'Province'),
                        MenuItem::resource(Region::class, 'Regioni'),
                    ])->icon('none')->collapsable(),
                ])->icon('globe')->collapsable(),

                // Arricchimenti
                MenuSection::make('Arricchimenti', [
                    MenuItem::resource(EcPoi::class, 'Punti di interesse'),
                    MenuItem::resource(MountainGroups::class, 'Gruppi Montuosi'),
                    MenuItem::resource(CaiHut::class, 'Rifugi'),
                    MenuItem::resource(NaturalSpring::class, 'Acqua Sorgente'),
                ])->icon('database')->collapsable(),

                // Rilievi
                MenuSection::make('Rilievi', [
                    MenuSection::make('Elementi rilevati', [
                        MenuItem::link('Pois', '/dashboards/main'),
                        MenuItem::link('Tracks', '/dashboards/main'),
                        MenuItem::link('Media', '/dashboards/main'),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Validazioni', [
                        MenuItem::link('Acqua Sorgente', '/dashboards/main'),
                        MenuItem::link('Segni dell’uomo', '/dashboards/main'),
                        MenuItem::link('Siti archeologici', '/dashboards/main'),
                        MenuItem::link('Aree archeologiche', '/dashboards/main'),
                        MenuItem::link('Siti archelogoche', '/dashboards/main'),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Export', [
                        MenuItem::link('Esporta Rilievi', '/dashboards/main'),
                    ])->icon('download')->collapsable(),
                ])->icon('eye')->collapsable(),

                // Tools
                MenuSection::make('Tools', [
                    MenuItem::link('Mappa Percorsi', '/dashboards/main'),
                    MenuItem::link('INFOMONT', '/dashboards/main'),
                    MenuItem::link('API', '/dashboards/main'),
                    MenuItem::link('Documentazione OSM2CAI', '/dashboards/main'),
                ])->icon('color-swatch')->collapsable(),

                // Admin
                MenuSection::make('Admin', [
                    MenuItem::resource(User::class, 'User'), // Usa User Nova resource
                    MenuItem::externalLink('Horizon', url('/horizon'))->openInNewTab(),
                    MenuItem::externalLink('Logs', url('/logs'))->openInNewTab(),
                ])->icon('settings'),
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
