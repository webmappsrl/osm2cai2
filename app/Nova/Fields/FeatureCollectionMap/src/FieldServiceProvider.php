<?php

namespace App\Nova\Fields\FeatureCollectionMap\src;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Nova::serving(function (ServingNova $event) {
            // Se in futuro aggiungiamo asset JS/CSS, li registriamo qui
            // Nova::mix('feature-collection-map', __DIR__.'/../dist/mix-manifest.json');
        });

        // Registra le view del FeatureCollectionMap
        $this->loadViewsFrom(__DIR__.'/../views', 'nova.fields.feature-collection-map');

        // Registra le route del FeatureCollectionMap
        Route::middleware(['nova'])
            ->prefix('nova-vendor/feature-collection-map')
            ->group(__DIR__.'/../Routes/api.php');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
