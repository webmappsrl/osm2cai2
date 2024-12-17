<?php

namespace App\Providers;

use App\Nova\Area;
use App\Nova\Club;
use App\Nova\Sign;
use App\Nova\User;
use App\Nova\EcPoi;
use App\Nova\Poles;
use App\Nova\CaiHut;
use App\Nova\Region;
use App\Nova\Sector;
use App\Nova\UgcPoi;
use App\Nova\Province;
use App\Nova\UgcMedia;
use App\Nova\UgcTrack;
use Laravel\Nova\Nova;
use App\Nova\Itinerary;
use App\Nova\HikingRoute;
use App\Nova\Municipality;
use App\Nova\SourceSurvey;
use App\Nova\NaturalSpring;
use App\Nova\GeologicalSite;
use App\Nova\MountainGroups;
use Illuminate\Http\Request;
use App\Nova\Dashboards\Main;
use App\Nova\Dashboards\Utenti;
use Laravel\Nova\Menu\MenuItem;
use App\Nova\ArchaeologicalArea;
use App\Nova\ArchaeologicalSite;
use Laravel\Nova\Menu\MenuSection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use App\Nova\Dashboards\AcquaSorgente;
use App\Nova\Dashboards\ItalyDashboard;
use App\Nova\Dashboards\Percorribilità;
use App\Nova\Dashboards\EcPoisDashboard;
use App\Nova\Dashboards\SALMiturAbruzzo;
use App\Nova\Dashboards\PercorsiFavoriti;
use App\Nova\Dashboards\SectorsDashboard;
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
                    MenuItem::link('Riepilogo nazionale', '/dashboards/italy-dashboard')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent', 'Regional Referent', 'Local Referent']);
                        }),

                    MenuItem::link('Percorsi Favoriti', '/dashboards/percorsi-favoriti')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent', 'Regional Referent', 'Local Referent']);
                        }),

                    MenuItem::link('POIS', '/dashboards/ec-pois')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent', 'Regional Referent']);
                        }),

                    MenuItem::link('Riepilogo utenti', '/dashboards/utenti')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent']);
                        }),

                    MenuItem::link('Riepilogo Percorribilità', '/dashboards/percorribilità')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent', 'Regional Referent', 'Local Referent']);
                        }),

                    MenuItem::link('Riepilogo MITUR-Abruzzo', '/dashboards/sal-mitur-abruzzo')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent']);
                        }),

                    MenuItem::link('Riepilogo Acqua Sorgente', '/dashboards/acqua-sorgente')
                        ->canSee(function () {
                            return auth()->user()->hasAnyRole(['Administrator', 'National Referent']);
                        }),

                    MenuItem::link('Riepilogo Settori', '/dashboards/settori')
                        ->canSee(function () {
                            return auth()->user()->hasRole('Regional Referent');
                        }),
                ])->icon('chart-bar')->collapsable(),

                // Rete Escursionistica
                MenuSection::make('Rete Escursionistica', [
                    MenuSection::make('Sentieri', [
                        MenuItem::resource(HikingRoute::class, 'Percorsi'),
                        MenuItem::resource(Itinerary::class, 'Itinerari'),
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

        if (! $loggedInUser) {
            return $dashboards;
        }

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
