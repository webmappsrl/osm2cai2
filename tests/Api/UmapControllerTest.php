<?php

namespace Tests\Api;

use App\Models\UgcPoi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UmapControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function createUgcPoi(string $formId, array $rawData): UgcPoi
    {
        return UgcPoi::factory()->createQuietly([
            'form_id' => $formId,
            'geohub_id' => '123',
            'geometry' => DB::raw('ST_GeomFromGeoJSON(\'{"type":"Point","coordinates":[10,10]}\')'),
            'raw_data' => $rawData,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Disable throttling for testing
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_pois_endpoint_returns_correct_structure()
    {
        $poi = $this->createUgcPoi('poi', [
            'waypointtype' => 'flora',
            'title' => 'Test POI',
            'description' => 'Test description',
        ]);

        $response = $this->getJson(url('/api/umap/pois'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'description',
                            'waypointtype',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'title' => 'Test POI',
                'description' => 'Test description',
                'waypointtype' => 'flora',
            ]);
    }

    public function test_signs_endpoint_returns_correct_structure()
    {
        $sign = $this->createUgcPoi('signs', [
            'artifact_type' => 'Test Artifact',
            'title' => 'Test Sign',
            'location' => 'Test Location',
            'conservation_status' => 'Test Conservation Status',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson(url('/api/umap/signs'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'artifact_type',
                            'location',
                            'conservation_status',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'title' => 'Test Sign',
                'artifact_type' => 'Test Artifact',
                'location' => 'Test Location',
                'conservation_status' => 'Test Conservation Status',
                'notes' => 'Test Notes',
            ]);
    }

    public function test_archaeological_sites_endpoint_returns_correct_structure()
    {
        $archaeologicalSite = $this->createUgcPoi('archaeological_site', [
            'title' => 'Test Archaeological Site',
            'location' => 'Test Location',
            'condition' => 'Test Condition',
            'informational_supports' => 'Test Informational Supports',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson(url('/api/umap/archaeological_sites'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'location',
                            'condition',
                            'informational_supports',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'title' => 'Test Archaeological Site',
                'location' => 'Test Location',
                'condition' => 'Test Condition',
                'informational_supports' => 'Test Informational Supports',
                'notes' => 'Test Notes',
            ]);
    }

    public function test_archaeological_areas_endpoint_returns_correct_structure()
    {
        $archaeologicalArea = $this->createUgcPoi('archaeological_area', [
            'title' => 'Test Archaeological Area',
            'area_type' => 'Test Area Type',
            'location' => 'Test Location',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson(url('/api/umap/archaeological_areas'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'area_type',
                            'location',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'title' => 'Test Archaeological Area',
                'area_type' => 'Test Area Type',
                'location' => 'Test Location',
                'notes' => 'Test Notes',
            ]);
    }

    public function test_geological_sites_endpoint_returns_correct_structure()
    {
        $geologicalSite = $this->createUgcPoi('geological_site', [
            'title' => 'Test Geological Site',
            'site_type' => 'Test Site Type',
            'vulnerability' => 'Test Vulnerability',
            'vulnerability_reasons' => 'Test Vulnerability Reasons',
            'ispra_geosite' => 'Test ISPRA Geosite',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson(url('/api/umap/geological_sites'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'site_type',
                            'vulnerability',
                            'vulnerability_reasons',
                            'ispra_geosite',
                            'notes',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'title' => 'Test Geological Site',
                'site_type' => 'Test Site Type',
                'vulnerability' => 'Test Vulnerability',
                'vulnerability_reasons' => 'Test Vulnerability Reasons',
                'ispra_geosite' => 'Test ISPRA Geosite',
                'notes' => 'Test Notes',
            ]);
    }

    public function test_pois_endpoint_returns_empty_when_no_data()
    {
        $response = $this->getJson(url('/api/umap/pois'));

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'FeatureCollection',
                'features' => [],
            ]);
    }
}
