<?php

use Illuminate\Support\Facades\Route;
use Wm\SignageMap\Http\Controllers\SignageMapController;

// Route per aggiornare le properties dell'hikingRoute tramite SignageMap
Route::patch('/hiking-route/{id}/properties', [SignageMapController::class, 'updateProperties'])
    ->name('signage-map.update-properties');
