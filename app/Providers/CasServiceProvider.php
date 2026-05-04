<?php

namespace App\Providers;

use App\Services\CasManager;
use Illuminate\Support\ServiceProvider;

class CasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cas', function ($app) {
            return new CasManager(config('cas'));
        });
    }

    public function boot(): void {}
}
