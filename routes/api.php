<?php

use App\Http\Controllers\CsvController;
use App\Http\Controllers\EcPoiController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GeojsonController;
use App\Http\Controllers\HikingRouteController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\MiturAbruzzoController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\ShapeFileController;
use App\Http\Controllers\SourceSurveyController;
use App\Http\Controllers\TrailSurveyController;
use App\Http\Controllers\UmapController;
use App\Http\Controllers\V1\HikingRoutesRegionControllerV1;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Wm\WmPackage\Http\Controllers\Api\UgcPoiController;
use Wm\WmPackage\Http\Controllers\Api\UgcTrackController;

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
// TODO check compatibility with geography type geometry
Route::prefix('shapefile')->name('shapefile.')->group(function () {
    Route::get('/{modelType}/{id}', [ShapeFileController::class, 'download']);
});
// TODO check compatibility with geography type geometry
Route::prefix('kml')->name('kml.')->group(function () {
    Route::get('/{modelType}/{id}', [KmlController::class, 'download']);
});
Route::prefix('geojson')->name('geojson.')->group(function () {
    Route::get('/{modelType}/{id}', [GeojsonController::class, 'download']);
    Route::prefix('ugc')->name('ugc.')->group(function () {
        Route::get('/ugcpoi/{ids}', [UgcPoiController::class, 'downloadGeojson'])->name('ugcpoi');
        Route::get('/ugctrack/{ids}', [UgcTrackController::class, 'downloadGeojson'])->name('ugctrack');
    });
});

Route::prefix('geojson-complete')->name('geojson_complete.')->group(function () {
    Route::get('/region/{id}', [RegionController::class, 'geojsonComplete'])->name('region');
    Route::get('/sector/{id}', [SectorController::class, 'geojsonComplete'])->name('sector');
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
    Route::get('/hiking-route-tdh/{id}', [HikingRouteController::class, 'showTdh'])->name('hr_tdh_by_id');
    Route::get('/hiking-route-osm/{osm_id}', [HikingRouteController::class, 'showByOsmId'])->name('hr_by_osmid');
    Route::get('/hiking-routes/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'indexByBoundingBox'])->name('hr-ids-by-bb');
    Route::get('/hiking-routes-osm/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'OsmIndexByBoundingBox'])->name('hr-osmids-by-bb');
    Route::get('/hiking-routes-collection/bb/{bounding_box}/{sda}', [HikingRouteController::class, 'collectionByBoundingBox'])->name('v2-hr-collection-by-bb');
    Route::get('/itinerary/list', [ItineraryController::class, 'index'])->name('v2-itinerary-list');
    Route::get('/itinerary/{id}', [ItineraryController::class, 'show'])->name('v2-itinerary-id');
    Route::get('/ecpois/bb/{bounding_box}/{type}', [EcPoiController::class, 'indexByBoundingBox'])->name('v2-ecpois-by-bb');
    Route::get('/ecpois/{hr_osm2cai_id}/{type}', [EcPoiController::class, 'indexByBufferFromHikingRouteId'])->name('v2-ecpois-by-osm2caiId');
    Route::get('/ecpois/osm/{hr_osm_id}/{type}', [EcPoiController::class, 'indexByBufferFromHikingRouteOsmId'])->name('v2-ecpois-by-OsmId');

    // Export
    Route::prefix('export')->name('export')->group(function () {
        Route::get('/hiking-routes/list', [ExportController::class, 'hikingRoutesList'])->name('hiking-routes-export');
        Route::get('/hiking-routes/{id}', [ExportController::class, 'hikingRoutesSingleFeature'])->name('hiking-routes-single-feature-export');
        Route::get('/users/list', [ExportController::class, 'usersList'])->name('users-export');
        Route::get('/users/{id}', [ExportController::class, 'usersSingleFeature'])->name('users-single-feature-export');
        Route::get('/ugc_pois/list', [ExportController::class, 'ugcPoisList'])->name('ugc-pois-export');
        Route::get('/ugc_pois/{id}', [ExportController::class, 'ugcPoisSingleFeature'])->name('ugc-pois-single-feature-export');
        Route::get('/ugc_tracks/list', [ExportController::class, 'ugcTracksList'])->name('ugc-tracks-export');
        Route::get('/ugc_tracks/{id}', [ExportController::class, 'ugcTracksSingleFeature'])->name('ugc-tracks-single-feature-export');
        Route::get('/ugc_media/list', [ExportController::class, 'ugcMediasList'])->name('ugc-medias-export');
        Route::get('/ugc_media/{id}', [ExportController::class, 'ugcMediasSingleFeature'])->name('ugc-medias-single-feature-export');
        Route::get('/areas/list', [ExportController::class, 'areasList'])->name('areas-export');
        Route::get('/areas/{id}', [ExportController::class, 'areasSingleFeature'])->name('areas-single-feature-export');
        Route::get('/sectors/list', [ExportController::class, 'sectorsList'])->name('sectors-export');
        Route::get('/sectors/{id}', [ExportController::class, 'sectorsSingleFeature'])->name('sectors-single-feature-export');
        Route::get('/sections/list', [ExportController::class, 'clubsList'])->name('clubs-export');
        Route::get('/sections/{id}', [ExportController::class, 'clubsSingleFeature'])->name('clubs-single-feature-export');
        Route::get('/itineraries/list', [ExportController::class, 'itinerariesList'])->name('itineraries-export');
        Route::get('/itineraries/{id}', [ExportController::class, 'itinerariesSingleFeature'])->name('itineraries-single-feature-export');
        Route::get('/ec_pois/list', [ExportController::class, 'ecPoisList'])->name('ec-pois-export');
        Route::get('/ec_pois/{id}', [ExportController::class, 'ecPoisSingleFeature'])->name('ec-pois-single-feature-export');
        Route::get('/ec_pois/osmfeatures/{osmfeaturesid}', [ExportController::class, 'ecPoisSingleFeatureByOsmfeaturesId'])->name('ec-pois-single-feature-by-osmfeatures-id-export');
        Route::get('/mountain_groups/list', [ExportController::class, 'mountainGroupsList'])->name('mountain-groups-export');
        Route::get('/mountain_groups/{id}', [ExportController::class, 'mountainGroupsSingleFeature'])->name('mountain-groups-single-feature-export');
        Route::get('/natural_springs/list', [ExportController::class, 'naturalSpringsList'])->name('natural-spring-export');
        Route::get('/natural_springs/{id}', [ExportController::class, 'naturalSpringsSingleFeature'])->name('natural-spring-single-feature-export');
        Route::get('/huts/list', [ExportController::class, 'hutsList'])->name('huts-export');
        Route::get('/huts/{id}', [ExportController::class, 'hutsSingleFeature'])->name('huts-single-feature-export');
    });
    Route::get('hiking-routes/{id}.gpx', [HikingRouteController::class, 'hikingRouteGpx'])->name('hiking-routes-gpx');

    // ACQUA SORGENTE
    Route::prefix('source_survey')->name('source-survey.')->group(function () {
        Route::get('/survey.geojson', [SourceSurveyController::class, 'surveyGeoJson'])->name('survey-geojson');
        Route::get('/survey.gpx', [SourceSurveyController::class, 'surveyGpx'])->name('survey-gpx');
        Route::get('/survey.kml', [SourceSurveyController::class, 'surveyKml'])->name('survey-kml');
        Route::get('/survey.shp', [SourceSurveyController::class, 'surveyShapefile'])->name('survey-shapefile');
        Route::get('/overlay.geojson', [SourceSurveyController::class, 'overlayGeoJson'])->name('overlay-geojson');
        Route::get('/monitorings', [SourceSurveyController::class, 'surveyData'])->name('monitorings');
    });

    // mitur_abruzzo
    Route::prefix('mitur_abruzzo')->name('v2-mitur-abruzzo')->group(function () {
        Route::get('/region_list', [MiturAbruzzoController::class, 'miturAbruzzoRegionList'])->name('region-list');
        Route::get('/region/{id}', [MiturAbruzzoController::class, 'miturAbruzzoRegionById'])->name('region-by-id');
        Route::get('/mountain_group/{id}', [MiturAbruzzoController::class, 'miturAbruzzoMountainGroupById'])->name('mountain-group-by-id');
        Route::get('/hiking_route/{id}', [MiturAbruzzoController::class, 'miturAbruzzoHikingRouteById'])->name('hiking-route-by-id');
        Route::get('/hut/{id}', [MiturAbruzzoController::class, 'miturAbruzzoHutById'])->name('hut-by-id');
        Route::get('/poi/{id}', [MiturAbruzzoController::class, 'miturAbruzzoPoiById'])->name('poi-by-id');
        Route::get('/section/{id}', [MiturAbruzzoController::class, 'miturAbruzzoClubById'])->name('section-by-id');
    });
});

Route::prefix('umap')->name('umap.')->group(function () {
    Route::get('/pois', [UmapController::class, 'pois'])->name('pois');
    Route::get('/signs', [UmapController::class, 'signs'])->name('signs');
    Route::get('/archaeological_sites', [UmapController::class, 'archaeologicalSites'])->name('archaeological_sites');
    Route::get('/archaeological_areas', [UmapController::class, 'archaeologicalAreas'])->name('archaeological_areas');
    Route::get('/geological_sites', [UmapController::class, 'geologicalSites'])->name('geological_sites');
});

// Webhook routes for geohub integration
Route::prefix('webhook')->name('webhook.')->group(function () {
    Route::post('/ugc/poi', [WebhookController::class, 'ugcPoi'])->name('ugc.poi');
    Route::post('/ugc/track', [WebhookController::class, 'ugcTrack'])->name('ugc.track');
});

// Trail Survey routes
Route::prefix('trail-surveys')->name('trail-surveys.')->group(function () {
    Route::get('/{id}/participants', [TrailSurveyController::class, 'getParticipants'])->name('participants');
});
