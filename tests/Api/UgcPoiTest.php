<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\UgcPoi;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UgcPoiTest extends TestCase
{
    use DatabaseTransactions;

    private string $baseUrl = '/api/ugc/poi/';
    private array $userData;
    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea un utente e ottieni il token
        $this->userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->otherUserData = [
            'name' => 'Other Test User',
            'email' => 'other@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/signup', $this->userData);
        $this->token = $response->json('access_token');
        $this->user = User::where('email', $this->userData['email'])->first();
    }

    /**
     * @test authenticated user can create poi
     */
    public function test_authenticated_user_can_create_poi()
    {
        $poiData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [10.0, 45.0]
            ],
            'properties' => [
                'name' => 'Test POI',
                'description' => 'Test Description',
                'id' => 'test_form',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $poiData);

        $response->assertStatus(201)
            ->assertJson([
                'id' => $response->json('id'),
                'message' => 'Created successfully'
            ]);

        $this->assertDatabaseHas('ugc_pois', [
            'name' => 'Test POI',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
            'form_id' => 'test_form',
            'app_id' => 'geohub_test_app'
        ]);
    }

    /**
     * @test unauthenticated user cannot create poi
     */
    public function test_unauthenticated_user_cannot_create_poi()
    {
        $poiData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [10.0, 45.0]
            ],
            'properties' => [
                'name' => 'Test POI',
                'id' => 'test_form'
            ]
        ];

        $response = $this->postJson($this->baseUrl . 'store', $poiData);

        $response->assertStatus(401);
    }

    /**
     * @test authenticated user can list pois
     */
    public function test_authenticated_user_can_list_pois()
    {
        // Crea alcuni POI di test
        UgcPoi::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'app_id' => 'geohub_123',
            'form_id' => 'test_form',
            'description' => 'Test Description',
            'raw_data' => 'Test Raw Data',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('" . json_encode([
                'type' => 'Point',
                'coordinates' => [10.0, 45.0]
            ]) . "')"),
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
                            'description',
                            'user_id',
                            'raw_data',
                            'form_id',
                            'validated',
                            'water_flow_rate_validated',
                            'app_id'
                        ]
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('features')));
    }

    /**
     * @test authenticated user can delete own poi
     */
    public function test_authenticated_user_can_delete_own_poi()
    {
        // Crea un POI
        $poi = UgcPoi::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson($this->baseUrl . 'delete/' . $poi->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseMissing('ugc_pois', [
            'id' => $poi->id
        ]);
    }

    /**
     * @test poi creation requires valid geometry
     */
    public function test_poi_creation_requires_valid_geometry()
    {
        $poiData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'InvalidType',
                'coordinates' => [10.0, 45.0]
            ],
            'properties' => [
                'name' => 'Test POI',
                'id' => 'test_form'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $poiData);

        $response->assertStatus(400);
    }

    /**
     * @test poi creation requires name
     */
    public function test_poi_creation_requires_name()
    {
        $poiData = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [10.0, 45.0]
            ],
            'properties' => [
                'id' => 'test_form'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', $poiData);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'properties.name' => ['validation.required'],
                    'properties.app_id' => ['validation.required']
                ],
                '0' => 'Validation Error'
            ]);
    }
}
