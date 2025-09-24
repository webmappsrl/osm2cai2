<?php

namespace App\Providers;

use App\Nova\App;
use App\Nova\ArchaeologicalArea;
use App\Nova\ArchaeologicalSite;
use App\Nova\Area;
use App\Nova\CaiHut;
use App\Nova\Club;
use App\Nova\Dashboards\AcquaSorgente;
use App\Nova\Dashboards\EcPoisDashboard;
use App\Nova\Dashboards\ItalyDashboard;
use App\Nova\Dashboards\Main;
use App\Nova\Dashboards\Percorribilità;
use App\Nova\Dashboards\PercorsiFavoriti;
use App\Nova\Dashboards\SALMiturAbruzzo;
use App\Nova\Dashboards\SectorsDashboard;
use App\Nova\Dashboards\Utenti;
use App\Nova\EcPoi;
use App\Nova\EcTrack;
use App\Nova\EnrichPoi;
use App\Nova\GeologicalSite;
use App\Nova\HikingRoute;
use App\Nova\Itinerary;
use App\Nova\Layer;
use App\Nova\MountainGroups;
use App\Nova\Municipality;
use App\Nova\NaturalSpring;
use App\Nova\Poles;
use App\Nova\Province;
use App\Nova\Region;
use App\Nova\Sector;
use App\Nova\Sign;
use App\Nova\SourceSurvey;
use App\Nova\TaxonomyActivity;
use App\Nova\TaxonomyPoiType;
use App\Nova\UgcMedia;
use App\Nova\UgcPoi;
use App\Nova\UgcTrack;
use App\Nova\User;
use App\Nova\User as NovaUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuGroup;
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

        Nova::style('custom-fields-css', public_path('css/custom-nova.css'));

        Nova::mainMenu(function (Request $request) {
            return [
                MenuSection::dashboard(Main::class)->icon('home'),
                MenuSection::make(__('MOBILE'), [
                    MenuItem::resource(App::class),
                    MenuItem::resource(NovaUser::class),
                    MenuSection::make(__('Editorial content'), [
                        MenuItem::resource(Layer::class),
                        MenuItem::resource(EcTrack::class),
                        MenuItem::resource(EcPoi::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('User Generated Content'), [
                        MenuItem::resource(UgcPoi::class),
                        MenuItem::resource(UgcTrack::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('Taxonomies'), [
                        MenuItem::resource(TaxonomyPoiType::class),
                        MenuItem::resource(TaxonomyActivity::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('Files'), [
                        MenuItem::externalLink(__('Icons'), route('icons.upload.show'))->openInNewTab(),
                    ])->collapsable(),
                ])->icon('globe')->collapsable()->canSee(function () {
                    return optional(Auth::user())->hasRole('Administrator');
                }),
                MenuGroup::make('', [
                    MenuItem::link(__('Riepilogo nazionale'), '/dashboards/italy-dashboard')
                        ->canSee(function () {
                            return true;
                        }),

                    MenuItem::link(__('Percorsi Favoriti'), '/dashboards/percorsi-favoriti')
                        ->canSee(function () {
                            return true;
                        }),

                    MenuItem::link(__('POIS'), '/dashboards/ec-pois')
                        ->canSee(function () {
                            return true;
                        }),

                    MenuItem::link(__('Riepilogo utenti'), '/dashboards/utenti')
                        ->canSee(function () {
                            return optional(Auth::user())->hasRole('Administrator');
                        }),

                    MenuItem::link(__('Riepilogo Percorribilità'), '/dashboards/percorribilità')
                        ->canSee(function () {
                            return optional(Auth::user())->hasAnyRole(['Administrator', 'National Referent', 'Regional Referent', 'Local Referent']);
                        }),

                    MenuItem::link(__('Riepilogo MITUR-Abruzzo'), '/dashboards/sal-mitur-abruzzo')
                        ->canSee(function () {
                            return optional(Auth::user())->hasAnyRole(['Administrator', 'National Referent']);
                        }),

                    MenuItem::link(__('Riepilogo Acqua Sorgente'), '/dashboards/acqua-sorgente')
                        ->canSee(function () {
                            return optional(Auth::user())->hasAnyRole(['Administrator', 'National Referent']);
                        }),

                    MenuItem::link(__('Riepilogo Settori'), '/dashboards/settori')
                        ->canSee(function () {
                            return optional(Auth::user())->hasRole('Regional Referent');
                        }),
                ]),

                // Rete Escursionistica
                MenuSection::make(__('Rete Escursionistica'), [
                    MenuSection::make(__('Sentieri'), [
                        MenuItem::resource(HikingRoute::class, __('Percorsi')),
                        MenuItem::resource(Itinerary::class, __('Itinerari')),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('Luoghi'), [
                        MenuItem::resource(Poles::class, __('Poles')),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('Unità Territoriali'), [
                        MenuItem::resource(Club::class),
                        MenuItem::resource(Municipality::class, __('Comuni')),
                        MenuItem::resource(Sector::class, __('Settori')),
                        MenuItem::resource(Area::class, __('Aree')),
                        MenuItem::resource(Province::class, __('Province')),
                        MenuItem::resource(Region::class, __('Regioni')),
                    ])->icon('none')->collapsable(),
                ])->icon('globe')->collapsable(),

                // Arricchimenti
                MenuSection::make(__('Arricchimenti'), [
                    MenuItem::resource(EnrichPoi::class, __('Punti di interesse')),
                    MenuItem::resource(MountainGroups::class, __('Gruppi Montuosi')),
                    MenuItem::resource(CaiHut::class, __('Rifugi')),
                    MenuItem::resource(NaturalSpring::class, __('Acqua Sorgente')),
                ])->icon('database')->collapsable(),

                // Rilievi
                MenuSection::make(__('Rilievi'), [
                    MenuSection::make(__('Elementi rilevati'), [
                        MenuItem::resource(UgcPoi::class, 'Ugc Poi'),
                        MenuItem::resource(UgcTrack::class),
                        MenuItem::resource(UgcMedia::class),
                    ])->icon('none')->collapsable(),
                    MenuSection::make(__('Validazioni'), [
                        MenuItem::resource(SourceSurvey::class),
                        MenuItem::resource(Sign::class),
                        MenuItem::resource(ArchaeologicalSite::class),
                        MenuItem::resource(ArchaeologicalArea::class),
                        MenuItem::resource(GeologicalSite::class),
                    ])->icon('none')->collapsable(),
                ])->icon('eye')->collapsable(),

                // Tools
                MenuSection::make(__('Tools'), [
                    MenuItem::externalLink(__('Mappa Settori'), 'http://osm2cai.j.webmapp.it/#/main/map?map=6.08,12.5735,41.5521')->openInNewTab(),
                    MenuItem::externalLink(__('Mappa Percorsi'), 'https://26.app.geohub.webmapp.it/#/map')->openInNewTab(),
                    MenuItem::externalLink(__('INFOMONT'), 'https://15.app.geohub.webmapp.it/#/map')->openInNewTab(),
                    MenuItem::externalLink(__('LoScarpone-Export'), route('loscarpone-export'))->openInNewTab(),
                    MenuItem::externalLink(__('API'), '/api/documentation')->openInNewTab(),
                    MenuItem::externalLink(__('Documentazione OSM2CAI'), 'https://catastorei.gitbook.io/documentazione-osm2cai')->openInNewTab(),
                    MenuItem::externalLink(__('Migration check'), route('migration-check'))->canSee(function () {
                        return optional(Auth::user())->hasRole('Administrator');
                    })->openInNewTab(),
                ])->icon('color-swatch')->collapsable(),

                // Admin
                MenuSection::make(__('Admin'), [
                    MenuItem::resource(User::class, __('User'))->canSee(function () {
                        return optional(Auth::user())->hasRole('Administrator') || optional(Auth::user())->hasRole('National Referent') || optional(Auth::user())->hasRole('Regional Referent');
                    }),
                    MenuItem::externalLink(__('Horizon'), url('/horizon'))->openInNewTab()->canSee(function () {
                        return optional(Auth::user())->hasRole('Administrator');
                    }),
                    MenuItem::externalLink(__('Logs'), url('/logs'))->openInNewTab()->canSee(function () {
                        return optional(Auth::user())->hasRole('Administrator');
                    }),
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
            return true;
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

        $loggedInUser = Auth::user();
        if (! $loggedInUser) {
            return $dashboards;
        }

        /** @var \App\Models\User $loggedInUser */
        if (method_exists($loggedInUser, 'hasRole') && $loggedInUser->hasRole('Administrator')) {
            $dashboards[] = new Utenti;
            $dashboards[] = new Percorribilità;
            $dashboards[] = new SALMiturAbruzzo;
            $dashboards[] = new AcquaSorgente;
        }

        if (method_exists($loggedInUser, 'hasRole') && $loggedInUser->hasRole('National Referent')) {
            $dashboards[] = new Percorribilità;
            $dashboards[] = new SALMiturAbruzzo;
            $dashboards[] = new AcquaSorgente;
        }

        if (method_exists($loggedInUser, 'hasRole') && $loggedInUser->hasRole('Regional Referent')) {
            $dashboards[] = new SectorsDashboard;
            $dashboards[] = new Percorribilità;
        }

        if (method_exists($loggedInUser, 'hasRole') && $loggedInUser->hasRole('Local Referent')) {
            $dashboards[] = new Percorribilità;
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
            new \Badinansoft\LanguageSwitch\LanguageSwitch,
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }

    // create a footer
    private function getFooter()
    {
        Nova::footer(function () {
            return Blade::render('nova/footer');
        });
    }
}
