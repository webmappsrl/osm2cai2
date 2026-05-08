<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        \App\Models\HikingRoute::class => \App\Policies\HikingRoutePolicy::class,
        \App\Models\SignageProject::class => \App\Policies\SignageProjectPolicy::class,
        \App\Models\TrailSurvey::class => \App\Policies\TrailSurveyPolicy::class,
        // Policies for the Sentiero Italia CAI content must be declared
        // explicitly: SiHikingRoute and SiMTBRoute extend HikingRoute, and
        // without an explicit mapping Laravel would resolve HikingRoutePolicy
        // via is_subclass_of, preventing the SicaiManager role from making
        // any modifications.
        \App\Models\SiHikingRoute::class => \App\Policies\SiHikingRoutePolicy::class,
        \App\Models\SiMTBRoute::class => \App\Policies\SiMTBRoutePolicy::class,
        \App\Models\SiPoi::class => \App\Policies\SiPoiPolicy::class,
        \Wm\WmPackage\Models\TaxonomyPoiType::class => \Wm\WmPackage\Policies\TaxonomyPoiTypePolicy::class,
        \Wm\WmPackage\Models\TaxonomyActivity::class => \Wm\WmPackage\Policies\TaxonomyActivityPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
