<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/auth/';
    private array $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123'
    ];
    private ?string $authToken = null;

    /**
     * Helper method to create and authenticate a user
     */
    private function createAndAuthenticateUser()
    {
        if (!$this->authToken) {
            $this->postJson($this->baseUrl . 'signup', $this->userData);
            $loginResponse = $this->postJson($this->baseUrl . 'login', [
                'email' => $this->userData['email'],
                'password' => $this->userData['password']
            ]);
            $this->authToken = $loginResponse->json('access_token');
        }
        return $this->authToken;
    }

    /**
     * Helper method to make authenticated requests
     */
    private function makeAuthenticatedRequest($method, $endpoint, $data = [])
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->createAndAuthenticateUser()
        ])->postJson($this->baseUrl . $endpoint, $data);
    }

    /**
     * @test that a user can successfully sign up with valid credentials
     */
    public function test_user_can_signup()
    {
        $response = $this->postJson($this->baseUrl . 'signup', $this->userData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'id',
                'name',
                'email',
                'email_verified_at'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $this->userData['email'],
            'name' => $this->userData['name']
        ]);
    }

    /**
     * @test that a user cannot sign up with an email that is already registered
     */
    public function test_user_cannot_signup_with_existing_email()
    {
        // First registration
        $this->postJson($this->baseUrl . 'signup', $this->userData);

        // Attempt registration with same email
        $response = $this->postJson($this->baseUrl . 'signup', $this->userData);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Un utente è già stato registrato con questa email.',
                'code' => 400
            ]);
    }

    /**
     * @test that a user can successfully login with valid credentials
     */
    public function test_user_can_login()
    {
        // Create a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);

        // Try login
        $response = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => $this->userData['password']
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'id',
                'name',
                'email',
                'email_verified_at'
            ]);
    }

    /**
     * @test that a user cannot login with incorrect credentials
     */
    public function test_user_cannot_login_with_wrong_credentials()
    {
        // Create a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);

        // Try login with wrong password
        $response = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'La password inserita non è corretta. Per favore, riprova.',
                'code' => 401
            ]);
    }

    /**
     * Test that an authenticated user can successfully logout
     */
    public function test_authenticated_user_can_logout()
    {
        $response = $this->makeAuthenticatedRequest('post', 'logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout effettuato con successo.'
            ]);
    }

    /**
     * @test that an authenticated user can retrieve their own information
     */
    public function test_authenticated_user_can_get_own_info()
    {
        $response = $this->makeAuthenticatedRequest('post', 'me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'email_verified_at'
            ]);
    }

    /**
     * @test that an authenticated user can delete their account
     */
    public function test_authenticated_user_can_delete_account()
    {
        $response = $this->makeAuthenticatedRequest('post', 'delete');

        $response->assertStatus(200)
            ->assertJson([
                'success' => 'Account utente cancellato con successo.'
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => $this->userData['email']
        ]);
    }

    /**
     * @test that a user can refresh their authentication token
     */
    public function test_user_can_refresh_token()
    {
        $oldToken = $this->createAndAuthenticateUser();
        $response = $this->makeAuthenticatedRequest('post', 'refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in'
            ]);

        $this->assertNotEquals($oldToken, $response->json('access_token'));
    }
}
