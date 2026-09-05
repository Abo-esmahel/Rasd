<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_api_login_with_valid_credentials(): void
    {
        $user = User::factory()->monitor()->create([
            'username' => 'testmonitor',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'testmonitor',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'username', 'role']],
            ]);
    }

    public function test_api_login_with_invalid_credentials(): void
    {
        $user = User::factory()->monitor()->create([
            'username' => 'testmonitor',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'testmonitor',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_api_login_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'nonexistent',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_protected_api_rejects_unauthenticated_users(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_protected_api_rejects_invalid_token(): void
    {
        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer invalid.token.here',
        ]);

        $response->assertUnauthorized();
    }

    public function test_api_me_returns_user_data(): void
    {
        $user = User::factory()->monitor()->create();
        $token = $this->jwtService->generateToken($user);

        $response = $this->getJson('/api/me', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ]);
    }

    public function test_api_logout_blacklists_token(): void
    {
        $user = User::factory()->monitor()->create();
        $token = $this->jwtService->generateToken($user);

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();

        $response2 = $this->getJson('/api/me', [
            'Authorization' => "Bearer $token",
        ]);

        $response2->assertUnauthorized();
    }
}
