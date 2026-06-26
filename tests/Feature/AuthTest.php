<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration.
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'user'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user'
        ]);
    }

    /**
     * Test user login and token generation.
     */
    public function test_user_can_login(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in'
            ]);
    }

    /**
     * Test user login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized'
            ]);
    }

    /**
     * Test getting authenticated user profile.
     */
    public function test_can_get_authenticated_user_profile(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'jane@example.com'
            ]);
    }

    /**
     * Test refreshing token.
     */
    public function test_can_refresh_token(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in'
            ]);
    }

    /**
     * Test logout.
     */
    public function test_can_logout(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out'
            ]);

        // Attempting to access profile again should be unauthenticated
        $profileResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $profileResponse->assertStatus(401);
    }

    /**
     * Test role-based authorization: admin accessing admin-dashboard.
     */
    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin-dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Welcome to the Admin Dashboard!'
            ]);
    }

    /**
     * Test role-based authorization: user cannot access admin-dashboard.
     */
    public function test_user_cannot_access_admin_dashboard(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin-dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden. You do not have the required role.'
            ]);
    }

    /**
     * Test role-based authorization: user accessing user-dashboard.
     */
    public function test_user_can_access_user_dashboard(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user-dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Welcome to the User Dashboard!'
            ]);
    }

    /**
     * Test role-based authorization: admin cannot access user-dashboard.
     */
    public function test_admin_cannot_access_user_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user-dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden. You do not have the required role.'
            ]);
    }
}
