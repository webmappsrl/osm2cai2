<?php

use App\Http\Controllers\CasLoginController;
use App\Http\Controllers\HikingRouteLoScarponeExportController;
use App\Http\Controllers\ImportUGCController;
use App\Http\Controllers\MigrationCheck;
use App\Http\Controllers\MiturAbruzzoController;
use App\Jobs\TestJob;
use App\Models\HikingRoute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth', 'nova'])->group(function () {
    Route::get('/migration-check', [MigrationCheck::class, 'show'])->name('migration-check');
});

Route::get('/logs', [Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

//Mitur Abruzzo Maps
Route::get('/poi/map/{id}', [MiturAbruzzoController::class, 'poiMap'])->name('poi-map');
Route::get('/mountain-groups/map/{id}', [MiturAbruzzoController::class, 'mountainGroupsMap'])->name('mountain-groups-map');
Route::get('/cai-huts/map/{id}', [MiturAbruzzoController::class, 'caiHutsMap'])->name('cai-huts-map');
Route::get('/mountain-groups-hr/map/{id}', [MiturAbruzzoController::class, 'mountainGroupsHrMap'])->name('mountain-groups-hr-map');
Route::get('/hiking-route/id/{id}', function ($id) {
    $hikingroute = HikingRoute::find($id);
    if ($hikingroute == null) {
        abort(404);
    }

    return view('hikingroute', [
        'hikingroute' => $hikingroute,
    ]);
})->name('hiking-route-public-page');

/**
 * Route to login to application with cas with specific middleware and controller
 */
Route::get('/nova/cas-login', CasLoginController::class . '@casLogin')
    ->middleware('cai.cas');

/**
 * Route to logout from application and cas with facade and specific middleware
 */
Route::get('/nova/cas-logout', function () {
    cas()->logout();
})->middleware('cas.auth');

Route::get('/loading-download/{type}/{model}/{id}', function () {
    return view('nova.loading', [
        'type' => request()->type,
        'model' => request()->model,
        'id' => request()->id,
    ]);
})->name('loading-download');

Route::get('loscarpone/export/', [HikingRouteLoScarponeExportController::class, 'export'])->name('loscarpone-export');
Route::get('hiking-route-map/{id}', function ($id) {
    return view('maps.hikingroute', ['hikingroute' => HikingRoute::findOrFail($id)]);
})->name('hiking-route-public-map');
