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
        \App\Models\TrailSurvey::class => \App\Policies\TrailSurveyPolicy::class,
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
