<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ShapeFileController;
use App\Http\Controllers\HikingRouteController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v2')->group(function () {
    Route::get('/hiking-routes/list', [HikingRouteController::class, 'index'])->name('hiking-routes-list');
});

Route::prefix('csv')->name('csv.')->group(function () {
    Route::get('/{modelType}/{id}', [CsvController::class, 'download'])->name('download');
});
Route::prefix('shapefile')->name('shapefile.')->group(function () {
    Route::get('/{modelType}/{id}', [ShapeFileController::class, 'download'])->name('download');
});
Route::prefix('kml')->name('kml.')->group(function () {
    Route::get('/{modelType}/{id}', [KmlController::class, 'download'])->name('download');
});
