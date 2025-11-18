<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Registra FeatureCollectionMap FieldServiceProvider
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the morphMap for polymorphic relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\Models\UgcPoi' => \Wm\WmPackage\Models\UgcPoi::class,
        ]);
    }
}
