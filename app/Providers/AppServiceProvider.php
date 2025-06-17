<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Wm\WmPackage\Services\Models\EcTrackService;
use App\Models\HikingRoute;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Configura EcTrackService per usare il modello HikingRoute
        $this->app->resolving(EcTrackService::class, function ($service, $app) {
            $service->setModel(new HikingRoute());
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
