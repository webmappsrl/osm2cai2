<?php

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Route;

// Route per il GeoJSON endpoint del FeatureCollectionMap
Route::get('/geojson/{id}', function ($id) {
    $hikingRoute = HikingRoute::findOrFail($id);
    $geojson = $hikingRoute->getFeatureCollectionMap();
    return response()->json($geojson);
})->name('feature-collection-map.geojson');

// Route per il widget della mappa
Route::get('/widget', function () {
    $geojsonParam = request()->get('geojson');
    
    // Se il parametro è un ID numerico, costruiamo l'URL del GeoJSON
    if (is_numeric($geojsonParam)) {
        $geojsonUrl = url("/nova-vendor/feature-collection-map/geojson/{$geojsonParam}");
    } else {
        // Altrimenti usiamo il parametro come URL diretto o default
        $geojsonUrl = $geojsonParam ?: 'https://sis-te.com/api/v1/catalog/geohub/1.geojson';
    }
    
    return view('nova.fields.feature-collection-map::feature-collection-map', ['geojsonUrl' => $geojsonUrl]);
})->name('feature-collection-map.widget');
