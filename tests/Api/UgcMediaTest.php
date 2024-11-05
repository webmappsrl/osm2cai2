<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\UgcMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class UgcMediaTest extends TestCase
{
    use DatabaseTransactions;

    private string $baseUrl = '/api/ugc/media/';
    private array $userData;
    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/signup', $this->userData);
        $this->token = $response->json('access_token');
        $this->user = User::where('email', $this->userData['email'])->first();
    }

    public function test_authenticated_user_can_create_media()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [10.0, 45.0]
            ],
            'properties' => [
                'name' => 'Test Media',
                'description' => 'Test Description',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', [
            'image' => $file,
            'geojson' => json_encode($geojson)
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'id' => $response->json('id'),
                'message' => 'Created successfully'
            ]);

        $this->assertDatabaseHas('ugc_media', [
            'name' => 'Test Media',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
            'app_id' => 'geohub_test_app'
        ]);

        Storage::disk('public')->assertExists($response->json('relative_url'));
    }

    public function test_unauthenticated_user_cannot_create_media()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $geojson = [
            'type' => 'Feature',
            'properties' => [
                'name' => 'Test Media',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->postJson($this->baseUrl . 'store', [
            'image' => $file,
            'geojson' => json_encode($geojson)
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_media()
    {
        $medias = UgcMedia::factory()->count(3)->create([
            'user_id' => $this->user->id,
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
                            'app_id',
                        ]
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('features')));
    }

    public function test_authenticated_user_can_delete_own_media()
    {
        Storage::fake('public');

        $media = UgcMedia::factory()->create([
            'user_id' => $this->user->id
        ]);

        // Crea un file finto nel filesystem
        Storage::disk('public')->put($media->relative_url, 'fake content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson($this->baseUrl . 'delete/' . $media->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => 'media deleted'
            ]);

        $this->assertDatabaseMissing('ugc_media', [
            'id' => $media->id
        ]);

        Storage::disk('public')->assertMissing($media->relative_url);
    }

    public function test_media_creation_requires_image()
    {
        $geojson = [
            'type' => 'Feature',
            'properties' => [
                'name' => 'Test Media',
                'app_id' => 'test_app'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', [
            'geojson' => json_encode($geojson)
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'image' => ['validation.required']
                ]
            ]);
    }

    public function test_media_creation_requires_geojson()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', [
            'image' => $file
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'geojson' => ['validation.required']
                ]
            ]);
    }

    public function test_media_creation_requires_app_id()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $geojson = [
            'type' => 'Feature',
            'properties' => [
                'name' => 'Test Media'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson($this->baseUrl . 'store', [
            'image' => $file,
            'geojson' => json_encode($geojson)
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'geojson.properties.app_id' => ['validation.required']
                ]
            ]);
    }
}
