<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\UgcTrack;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UgcTrackTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/ugc/track/';
    private array $userData;
    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/signup', $this->userData);
        $this->token = $response->json('access_token');
        $this->user = User::where('email', $this->userData['email'])->first();
    }

    /**
     * @test authenticated user can create track
     */
    public function test_authenticated_user_can_create_track()
    {
        $trackData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [10.0, 45.0, 0],
                    [10.1, 45.1, 0],
                    [10.2, 45.2, 0]
                ]
            ],
            'properties' => [
                'name' => 'Test Track',
                'description' => 'Test Description',
                'app_id' => 'test_app',
                'metadata' => [
                    'distance' => 1000,
                    'duration' => 3600
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $trackData);

        $response->assertStatus(201)
            ->assertJson([
                'id' => $response->json('id'),
                'message' => 'Created successfully'
            ]);

        $this->assertDatabaseHas('ugc_tracks', [
            'name' => 'Test Track',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
            'app_id' => 'geohub_test_app'
        ]);
    }

    /**
     * @test unauthenticated user cannot create track
     */
    public function test_unauthenticated_user_cannot_create_track()
    {
        $trackData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[10.0, 45.0], [10.1, 45.1]]
            ],
            'properties' => [
                'name' => 'Test Track',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->postJson($this->baseUrl . 'store', $trackData);

        $response->assertStatus(401);
    }

    /**
     * @test authenticated user can list tracks
     */
    public function test_authenticated_user_can_list_tracks()
    {
        UgcTrack::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'name' => 'Test Track',
            'app_id' => 'geohub_123',
            'description' => 'Test Description',
            'raw_data' => ['test_key' => 'test_value'],
            'geometry' => DB::raw("ST_GeomFromGeoJSON('" . json_encode([
                'type' => 'LineString',
                'coordinates' => [[10.0, 45.0, 0], [10.1, 45.1, 0], [10.2, 45.2, 0]]
            ]) . "')"),
            'metadata' => json_encode(['distance' => 1000, 'duration' => 3600])
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson($this->baseUrl . 'index');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'id',
                            'created_at',
                            'updated_at',
                            'name',
                            'description',
                            'user_id',
                            'raw_data',
                            'metadata',
                            'app_id',
                            'validated'
                        ]
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('features')));
    }

    /**
     * @test authenticated user can delete own track
     */
    public function test_authenticated_user_can_delete_own_track()
    {
        $track = UgcTrack::factory()->create([
            'user_id' => $this->user->id,
            'app_id' => 'geohub_123',
            'name' => 'Test Track',
            'description' => 'Test Description',
            'raw_data' => ['test_key' => 'test_value'],
            'geometry' => DB::raw("ST_GeomFromGeoJSON('" . json_encode([
                'type' => 'LineString',
                'coordinates' => [[10.0, 45.0, 0], [10.1, 45.1, 0], [10.2, 45.2, 0]]
            ]) . "')"),
            'metadata' => json_encode(['distance' => 1000, 'duration' => 3600])
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson($this->baseUrl . 'delete/' . $track->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => 'track deleted'
            ]);

        $this->assertDatabaseMissing('ugc_tracks', [
            'id' => $track->id
        ]);
    }


    /**
     * @test track creation requires name
     */
    public function test_track_creation_requires_name()
    {
        $trackData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[10.0, 45.0, 0], [10.1, 45.1, 0], [10.2, 45.2, 0]]
            ],
            'properties' => []
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $trackData);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'properties.name' => ['validation.required'],
                    'properties.app_id' => ['validation.required']
                ],
                '0' => 'Validation Error'
            ]);
    }

    /**
     * @test track creation requires app_id
     */
    public function test_track_creation_requires_app_id()
    {
        $trackData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[10.0, 45.0, 0], [10.1, 45.1, 0], [10.2, 45.2, 0]]
            ],
            'properties' => [
                'name' => 'Test Track'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $trackData);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'properties.app_id' => ['validation.required']
                ],
                '0' => 'Validation Error'
            ]);
    }

    /**
     * @test track creation requires geometry
     */
    public function test_track_creation_requires_geometry()
    {
        $trackData = [
            'type' => 'Feature',
            'geometry' => [],
            'properties' => [
                'name' => 'Test Track',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $trackData);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'geometry' => ['validation.required']
                ],
                '0' => 'Validation Error'
            ]);
    }
}
