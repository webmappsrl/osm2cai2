<?php

namespace Tests\Api;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Jobs\CacheMiturAbruzzoData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MiturAbruzzoApiTest extends TestCase
{

    use RefreshDatabase;
    /**
     * Legacy api samples data to test.
     *
     * @var array
     */
    protected $legacyEndpoints = [
        'Region' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/region/2',
        'MountainGroups' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/mountain_group/1940',
        'HikingRoute' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/hiking_route/4883',
        'EcPoi' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/poi/7563',
        'CaiHut' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/hut/345',
        'Club' => 'https://osm2cai.cai.it/api/v2/mitur_abruzzo/section/23',
    ];

    /**
     * Directory for saved geojson files.
     *
     * @var string
     */
    protected $stubDirectory = 'tests/stubs/mitur';

    /**
     * Test the consistency of the API structure with the saved GeoJSON file.
     *
     * @return void
     */
    public function testGeojsonStructureIsTheSameAsTheLegacyApi()
    {
        foreach ($this->legacyEndpoints as $model => $url) {
            $geoJsonPath = $this->stubDirectory . '/' . $model . '.geojson';

            // Check if the file exists, otherwise download it
            if (!Storage::exists($geoJsonPath)) {
                $response = Http::get($url);

                $this->assertTrue($response->successful(), "Errore nella risposta dell'API per {$model}");

                // Save the GeoJSON file content
                Storage::put($geoJsonPath, $response->body());
            }

            // Load the geojson file for comparison
            $geoJsonData = json_decode(Storage::get($geoJsonPath), true);

            $modelsGeometries = [
                'Region' => 'POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))',
                'MountainGroups' => 'POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))',
                'HikingRoute' => 'LINESTRING(0 0, 1 1)',
                'EcPoi' => 'POINT(0 0)',
                'CaiHut' => 'POINT(0 0)',
                'Club' => 'POINT(0 0)',
            ];

            $modelClass = "App\\Models\\{$model}";
            $columns = ['geometry' => DB::raw("ST_GeomFromText('{$modelsGeometries[$model]}')")];
            if ($model !== 'HikingRoute') {
                $columns['name'] = 'test';
            }
            if ($model === 'Club') {
                $columns['cai_code'] = 'TEST';
            }
            $modelInstance = $modelClass::factory()->create($columns);

            // Dispatch job to get new API response
            $job = new CacheMiturAbruzzoData(class_basename($modelInstance), $modelInstance->id, true);
            $job->handle();

            $newApiResponse = json_decode(Storage::get($this->stubDirectory . '/aws/' . class_basename($modelInstance) . '_' . $modelInstance->id . '.json'), true);

            $this->assertIsArray($newApiResponse);
            $this->assertNotEmpty($newApiResponse);

            // Verify that the GeoJSON structure matches the new API
            $this->assertGeoJsonStructureMatchesApi($geoJsonData, $newApiResponse);

            //after the test, delete the file in the stub directory
            Storage::delete($this->stubDirectory . '/aws/' . class_basename($modelInstance) . '_' . $modelInstance->id . '.json');
        }
    }

    /**
     * Compare the GeoJSON structure with the API response.
     *
     * @param array $legacyData
     * @param array $apiData
     * @return void
     */
    protected function assertGeoJsonStructureMatchesApi(array $legacyData, array $apiData)
    {
        // Check top level structure
        $this->assertEquals(
            array_keys($legacyData),
            array_keys($apiData),
            'Top level structure does not match'
        );

        // Check type field
        $this->assertEquals(
            $legacyData['type'],
            $apiData['type'],
            'Feature type does not match'
        );

        // Check properties structure
        $this->assertEquals(
            array_keys($legacyData['properties']),
            array_keys($apiData['properties']),
            'Properties structure does not match'
        );

        // Check geometry structure
        $this->assertEquals(
            array_keys($legacyData['geometry']),
            array_keys($apiData['geometry']),
            'Geometry structure does not match'
        );

        // Check geometry type
        $this->assertEquals(
            $legacyData['geometry']['type'],
            $apiData['geometry']['type'],
            'Geometry type does not match'
        );

        // Check coordinates structure exists
        $this->assertArrayHasKey('coordinates', $legacyData['geometry']);
        $this->assertArrayHasKey('coordinates', $apiData['geometry']);
    }
}
