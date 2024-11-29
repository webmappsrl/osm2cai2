<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Blade};
use Laravel\Nova\Menu\{MenuItem, MenuSection};
use Laravel\Nova\{NovaApplicationServiceProvider, Nova};
use App\Nova\Dashboards\{Main, Utenti, AcquaSorgente, ItalyDashboard, Percorribilità, EcPoisDashboard, PercorsiFavoriti, SectorsDashboard, SALMiturAbruzzo};
use App\Nova\{Area, Club, Sign, User, EcPoi, Poles, CaiHut, Region, Sector, UgcPoi, Province, UgcMedia, UgcTrack, HikingRoute, Municipality, SourceSurvey, NaturalSpring, GeologicalSite, MountainGroups, ArchaeologicalArea, ArchaeologicalSite};

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
                    MenuItem::link('Riepilogo nazionale', '/dashboards/italy-dashboard'),
                    MenuItem::link('Percorsi Favoriti', '/dashboards/percorsi-favoriti'),
                    MenuItem::link('POIS', '/dashboards/ec-pois'),
                    MenuItem::link('Riepilogo utenti', '/dashboards/utenti'),
                    MenuItem::link('Riepilogo Percorribilità', '/dashboards/percorribilità'),
                    MenuItem::link('Riepilogo MITUR-Abruzzo', '/dashboards/sal-mitur-abruzzo'),
                    MenuItem::link('Riepilogo Acqua Sorgente', '/dashboards/acqua-sorgente'),
                    MenuItem::link('Riepilogo Settori', '/dashboards/settori'),
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
                        MenuItem::resource(Club::class),
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
                        MenuItem::resource(UgcPoi::class),
                        MenuItem::resource(UgcTrack::class),
                        MenuItem::resource(UgcMedia::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Validazioni', [
                        MenuItem::resource(SourceSurvey::class),
                        MenuItem::resource(Sign::class),
                        MenuItem::resource(ArchaeologicalSite::class),
                        MenuItem::resource(ArchaeologicalArea::class),
                        MenuItem::resource(GeologicalSite::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make('Export', [
                        MenuItem::link('Esporta Rilievi', '/dashboards/main'),
                    ])->icon('download')->collapsable(),
                ])->icon('eye')->collapsable(),

                // Tools
                MenuSection::make('Tools', [
                    MenuItem::link('Mappa Settori', 'http://osm2cai.j.webmapp.it/#/main/map?map=6.08,12.5735,41.5521'),
                    MenuItem::link('Mappa Percorsi', 'https://26.app.geohub.webmapp.it/#/map'),
                    MenuItem::link('INFOMONT', 'https://15.app.geohub.webmapp.it/#/map'),
                    MenuItem::link('API', '/api/documentation'),
                    MenuItem::link('Documentazione OSM2CAI', 'https://catastorei.gitbook.io/documentazione-osm2cai'),
                ])->icon('color-swatch')->collapsable(),

                // Admin
                MenuSection::make('Admin', [
                    MenuItem::resource(User::class, 'User'), // Usa User Nova resource
                    MenuItem::externalLink('Horizon', url('/horizon'))->openInNewTab(),
                    MenuItem::externalLink('Logs', url('/logs'))->openInNewTab(),
                ])->icon('user'),
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

        $dashboards = [
            new ItalyDashboard,
            new PercorsiFavoriti,
            new EcPoisDashboard,
            new Main,
        ];

        $loggedInUser = auth()->user();

        if ($loggedInUser->hasRole('Administrator')) {
            $dashboards[] = new Utenti();
            $dashboards[] = new Percorribilità();
            $dashboards[] = new SALMiturAbruzzo();
            $dashboards[] = new AcquaSorgente();
        }

        if ($loggedInUser->hasRole('National Referent')) {
            $dashboards[] = new Percorribilità();
            $dashboards[] = new SALMiturAbruzzo();
            $dashboards[] = new AcquaSorgente();
        }

        if ($loggedInUser->hasRole('Regional Referent')) {

            $dashboards[] = new SectorsDashboard;
            $dashboards[] = new Percorribilità($loggedInUser); //show data only for the user region
        }

        if ($loggedInUser->hasRole('Local Referent')) {
            $dashboards[] = new Percorribilità($loggedInUser);
        }

        return $dashboards;
    }


    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [
            \Vyuldashev\NovaPermission\NovaPermissionTool::make(),
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
