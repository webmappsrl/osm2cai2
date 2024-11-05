<?php

namespace Tests\Feature;

use App\Models\User;
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
        // Create and authenticate a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);
        $loginResponse = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => $this->userData['password']
        ]);

        $token = $loginResponse->json('access_token');

        // Try logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson($this->baseUrl . 'logout');

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
        // Create and authenticate a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);
        $loginResponse = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => $this->userData['password']
        ]);

        $token = $loginResponse->json('access_token');

        // Request user info
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson($this->baseUrl . 'me');

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
        // Create and authenticate a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);
        $loginResponse = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => $this->userData['password']
        ]);

        $token = $loginResponse->json('access_token');

        // Delete account
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson($this->baseUrl . 'delete');

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
        // Create and authenticate a user
        $this->postJson($this->baseUrl . 'signup', $this->userData);
        $loginResponse = $this->postJson($this->baseUrl . 'login', [
            'email' => $this->userData['email'],
            'password' => $this->userData['password']
        ]);

        $token = $loginResponse->json('access_token');

        // Refresh the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson($this->baseUrl . 'refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in'
            ]);

        $this->assertNotEquals($token, $response->json('access_token'));
    }
}
