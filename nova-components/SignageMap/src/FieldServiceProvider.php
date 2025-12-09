<?php

namespace Osm2cai\SignageMap;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Nova::serving(function (ServingNova $event) {
            Nova::script('signage-map', __DIR__ . '/../dist/js/field.js');
            Nova::style('signage-map', __DIR__ . '/../dist/css/field.css');
        });

        // Registra le route del SignageMap
        $this->loadRoutes();
    }

    /**
     * Load the field routes.
     */
    protected function loadRoutes(): void
    {
        Route::middleware(['nova'])
            ->prefix('nova-vendor/signage-map')
            ->group(__DIR__ . '/Routes/api.php');
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
}
