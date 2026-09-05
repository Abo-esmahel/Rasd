<?php

namespace Tests\Feature\Web;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('تسجيل الدخول');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->monitor()->create([
            'username' => 'testmonitor',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'username' => 'testmonitor',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->monitor()->create([
            'username' => 'testmonitor',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'username' => 'testmonitor',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->monitor()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
