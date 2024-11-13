<?php

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\EcPoiController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\GeojsonController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\ShapeFileController;
use App\Http\Resources\HikingRouteTDHResource;
use App\Http\Controllers\HikingRouteController;
use App\Http\Controllers\SourceSurveyController;
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
    Route::get('/hiking-route-tdh/{id}', [HikingRouteController::class, 'showTdh'])->name('hr_thd_by_id');
    Route::get('/hiking-route-osm/{osm_id}', [HikingRouteController::class, 'showByOsmId'])->name('hr_by_osmid');
    Route::get('/hiking-routes/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'indexByBoundingBox'])->name('hr-ids-by-bb');
    Route::get('/hiking-routes-osm/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'OsmIndexByBoundingBox'])->name('hr-osmids-by-bb');
    Route::get('/hiking-routes-collection/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'collectionByBoundingBox'])->name('v2-hr-collection-by-bb');
    Route::get('/itinerary/list', [ItineraryController::class, 'index'])->name('v2-itinerary-list');
    Route::get('/itinerary/{id}', [ItineraryController::class, 'show'])->name('v2-itinerary-id');
    Route::get('/ecpois/bb/{bounding_box}/{type}', [EcPoiController::class, 'indexByBoundingBox'])->name('v2-ecpois-by-bb');
    Route::get('/ecpois/{hr_osm2cai_id}/{type}', [EcPoiController::class, 'indexByBufferFromHikingRouteId'])->name('v2-ecpois-by-osm2caiId');
    Route::get('/ecpois/{hr_osm_id}/{type}', [EcPoiController::class, 'indexByBufferFromHikingRouteOsmId'])->name('v2-ecpois-by-OsmId');


    //ACQUA SORGENTE
    Route::prefix('source_survey')->name('source-survey.')->group(function () {
        Route::get('/survey.geojson', [SourceSurveyController::class, 'surveyGeoJson'])->name('survey-geojson');
        Route::get('/survey.gpx', [SourceSurveyController::class, 'surveyGpx'])->name('survey-gpx');
        Route::get('/survey.kml', [SourceSurveyController::class, 'surveyKml'])->name('survey-kml');
        Route::get('/survey.shp', [SourceSurveyController::class, 'surveyShapefile'])->name('survey-shapefile');
        Route::get('/overlay.geojson', [SourceSurveyController::class, 'overlayGeoJson'])->name('overlay-geojson');
    });

    //mitur_abruzzo
    Route::prefix('mitur_abruzzo')->name('v2-mitur-abruzzo')->group(function () {
        Route::get('/region_list', [MiturAbruzzoController::class, 'miturAbruzzoRegionList'])->name('region-list');
        Route::get('/region/{id}', [MiturAbruzzoController::class, 'miturAbruzzoRegionById'])->name('region-by-id');
        Route::get('/mountain_group/{id}', [MiturAbruzzoController::class, 'miturAbruzzoMountainGroupById'])->name('mountain-group-by-id');
        Route::get('/hiking_route/{id}', [MiturAbruzzoController::class, 'miturAbruzzoHikingRouteById'])->name('hiking-route-by-id');
        Route::get('/hut/{id}', [MiturAbruzzoController::class, 'miturAbruzzoHutById'])->name('hut-by-id');
        Route::get('/poi/{id}', [MiturAbruzzoController::class, 'miturAbruzzoPoiById'])->name('poi-by-id');
        Route::get('/section/{id}', [MiturAbruzzoController::class, 'miturAbruzzoSectionById'])->name('section-by-id');
    });
});
