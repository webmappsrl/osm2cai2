<?php

use Illuminate\Support\Facades\Route;
use Osm2cai\SignageMap\Http\Controllers\SignageMapController;

// Route per aggiornare le properties dell'hikingRoute tramite SignageMap
Route::patch('/hiking-route/{id}/properties', [SignageMapController::class, 'updateProperties'])
    ->name('signage-map.update-properties');

// Route per suggerire un nome località tramite Nominatim reverse geocoding
Route::get('/pole/{poleId}/suggest-place-name', [SignageMapController::class, 'suggestPlaceName'])
    ->name('signage-map.suggest-place-name');

// Route per aggiornare la direzione di una freccia nella segnaletica di un palo
Route::patch('/pole/{poleId}/arrow-direction', [SignageMapController::class, 'updateArrowDirection'])
    ->name('signage-map.update-arrow-direction');

// Route per aggiornare l'ordine delle frecce di un palo
Route::patch('/pole/{poleId}/arrow-order', [SignageMapController::class, 'updateArrowOrder'])
    ->name('signage-map.update-arrow-order');
