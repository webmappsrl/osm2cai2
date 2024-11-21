<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\UgcPoi;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UmapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pois_endpoint_returns_correct_structure()
    {
        $poi = UgcPoi::factory()->create([
            'form_id' => 'poi',
            'geohub_id' => '123',
            'raw_data' => [
                'waypointtype' => 'flora',
                'title' => 'Test POI',
                'description' => 'Test description',
            ],
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
                            'geohub_link',
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
        $sign = UgcPoi::factory()->create([
            'form_id' => 'signs',
            'geohub_id' => '123',
            'raw_data' => [
                'artifact_type' => 'Test Artifact',
                'title' => 'Test Sign',
                'location' => 'Test Location',
                'conservation_status' => 'Test Conservation Status',
                'notes' => 'Test Notes',
            ],
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
                            'geohub_link',
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
        $archaeologicalSite = UgcPoi::factory()->create([
            'form_id' => 'archaeological_site',
            'geohub_id' => '123',
            'raw_data' => [
                'title' => 'Test Archaeological Site',
                'location' => 'Test Location',
                'condition' => 'Test Condition',
                'informational_supports' => 'Test Informational Supports',
                'notes' => 'Test Notes',
            ],
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
                            'geohub_link',
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
        $archaeologicalArea = UgcPoi::factory()->create([
            'form_id' => 'archaeological_area',
            'geohub_id' => '123',
            'raw_data' => [
                'title' => 'Test Archaeological Area',
                'area_type' => 'Test Area Type',
                'location' => 'Test Location',
                'notes' => 'Test Notes',
            ],
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
                            'geohub_link',
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
        $geologicalSite = UgcPoi::factory()->create([
            'form_id' => 'geological_site',
            'geohub_id' => '123',
            'raw_data' => [
                'title' => 'Test Geological Site',
                'site_type' => 'Test Site Type',
                'vulnerability' => 'Test Vulnerability',
                'vulnerability_reasons' => 'Test Vulnerability Reasons',
                'ispra_geosite' => 'Test ISPRA Geosite',
                'notes' => 'Test Notes',
            ],
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
