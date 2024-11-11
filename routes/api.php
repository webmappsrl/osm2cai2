<?php

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\GeojsonController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ShapeFileController;
use App\Http\Resources\HikingRouteTDHResource;
use App\Http\Controllers\HikingRouteController;
use App\Http\Controllers\V1\HikingRoutesRegionControllerV1;
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

Route::prefix('csv')->name('csv.')->group(function () {
    Route::get('/{modelType}/{id}', [CsvController::class, 'download']);
});
//TODO check compatibility with geography type geometry
Route::prefix('shapefile')->name('shapefile.')->group(function () {
    Route::get('/{modelType}/{id}', [ShapeFileController::class, 'download']);
});
//TODO check compatibility with geography type geometry
Route::prefix('kml')->name('kml.')->group(function () {
    Route::get('/{modelType}/{id}', [KmlController::class, 'download']);
});
Route::prefix('geojson')->name('geojson.')->group(function () {
    Route::get('/{modelType}/{id}', [GeojsonController::class, 'download']);
});

Route::prefix('v1')->name('v1')->group(function () {
    Route::get('/hiking-routes/region/{regione_code}/{sda}', [HikingRoutesRegionControllerV1::class, 'hikingroutelist'])->name('hr-ids-by-region');
    Route::get('/hiking-routes-osm/region/{regione_code}/{sda}', [HikingRoutesRegionControllerV1::class, 'hikingrouteosmlist'])->name('hr_osmids_by_region');
    Route::get('/hiking-route/{id}', [HikingRoutesRegionControllerV1::class, 'hikingroutebyid'])->name('hr_by_id');
    Route::get('/hiking-route-osm/{id}', [HikingRoutesRegionControllerV1::class, 'hikingroutebyosmid'])->name('hr_by_osmid');
    Route::get('/hiking-routes/bb/{bounding_box}/{sda}', [HikingRoutesRegionControllerV1::class, 'hikingroutelist_bb'])->name('hr-ids-by-bb');
    Route::get('/hiking-routes-osm/bb/{bounding_box}/{sda}', [HikingRoutesRegionControllerV1::class, 'hikingrouteosmlist_bb'])->name('hr-osmids-by-bb');
    Route::get('/hiking-routes-collection/bb/{bounding_box}/{sda}', [HikingRoutesRegionControllerV1::class, 'hikingroutelist_collection'])->name('hr-collection-by-bb');
});



Route::prefix('v2')->group(function () {
    Route::get('/hiking-routes/list', [HikingRouteController::class, 'index'])->name('hr-list');
    Route::get('/hiking-routes/region/{regione_code}/{sda}', [HikingRouteController::class, 'indexByRegion'])->name('hr-ids-by-region');
    Route::get('/hiking-routes-osm/region/{regione_code}/{sda}', [HikingRouteController::class, 'OsmIndexByRegion'])->name('hr_osmids_by_region');
    Route::get('/hiking-route/{id}', [HikingRouteController::class, 'show'])->name('hr_by_id');
    Route::get('/hiking-route-tdh/{id}', function (string $id) {
        return new HikingRouteTDHResource(HikingRoute::findOrFail($id));
    })->name('hr_thd_by_id');
    Route::get('/hiking-route-osm/{osm_id}', [HikingRouteController::class, 'showByOsmId'])->name('hr_by_osmid');
    Route::get('/hiking-routes/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'indexByBoundingBox'])->name('hr-ids-by-bb');
    Route::get('/hiking-routes-osm/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'OsmIndexByBoundingBox'])->name('hr-osmids-by-bb');
});
