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

        // oc:8867 — lo store del package duplica i rilevamenti a ogni retry dell'app (uuid letto da
        // $request->input() invece che dai dati validati). Finché wm-package non è aggiornato, le rotte
        // del package risolvono questi controller. Le rotte non vengono riscritte: il package carica lo
        // stesso file due volte (prefissi api/v2 e api) e riusarne i nomi farebbe fallire route:cache.
        // STRATO 1 — NON CANCELLARE (oc:8867): il file resta, va tolto solo questo blocco (i due bind)
        // all'aggiornamento di wm-package: i controller del package sono già quelli giusti.
        $this->app->bind(\Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class, \App\Http\Controllers\Api\UgcPoiController::class);
        $this->app->bind(\Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class, \App\Http\Controllers\Api\UgcTrackController::class);
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
