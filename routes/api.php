<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UgcPoiController;
use App\Http\Controllers\UgcMediaController;
use App\Http\Controllers\UgcTrackController;
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

Route::name('api.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
    Route::middleware('throttle:100,1')->post('/auth/signup', [AuthController::class, 'signup'])->name('signup');
    Route::group([
        'middleware' => 'auth.jwt',
        'prefix' => 'auth'
    ], function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::post('me', [AuthController::class, 'me'])->name('me');
        Route::post('delete', [AuthController::class, 'delete'])->name('delete');
    });
});

Route::group([
    'middleware' => 'auth.jwt',
], function () {
    Route::prefix('ugc')->name('ugc.')->group(function () {
        Route::prefix('poi')->name('poi.')->group(function () {
            Route::post("store", [UgcPoiController::class, 'store'])->name('store');
            Route::get("index", [UgcPoiController::class, 'index'])->name('index');
            Route::delete('delete/{id}', [UgcPoiController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('track')->name('track.')->group(function () {
            Route::post("store", [UgcTrackController::class, 'store'])->name('store');
            Route::get("index", [UgcTrackController::class, 'index'])->name('index');
            Route::delete('delete/{id}', [UgcTrackController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('media')->name('media.')->group(function () {
            Route::post("store", [UgcMediaController::class, 'store'])->name('store');
            Route::get("index", [UgcMediaController::class, 'index'])->name('index');
            Route::delete('delete/{id}', [UgcMediaController::class, 'destroy'])->name('destroy');
        });
    });
});

Route::prefix('v2')->group(function () {
    Route::get('/hiking-routes/list', [HikingRouteController::class, 'index'])->name('hiking-routes-list');
});
