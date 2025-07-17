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
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register the morphMap for polymorphic relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\Models\WmUgcPoi' => \Wm\WmPackage\Models\UgcPoi::class,
        ]);
    }
}
