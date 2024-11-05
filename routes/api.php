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

Route::prefix('v2')->group(function () {
    Route::get('/hiking-routes/list', [HikingRouteController::class, 'index'])->name('hiking-routes-list');
});
