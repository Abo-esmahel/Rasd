<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ملف أي مستخدم عبر الـAPI — ما يغذي شاشة الملف الشخصي في فلاتر.
 *
 * يجب أن يطابق الويب في إخفاء المسودات عن الغريب، وإلا انكشفت الأرقام
 * في اتجاه (الجوال) وهي مخفية في الاتجاه الآخر (المتصفح).
 */
class UserApiProfileTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $u): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->generateToken($u)];
    }

    #[Test]
    public function shows_another_users_profile_with_stats(): void
    {
        $viewer = User::factory()->monitor()->create();
        $other = User::factory()->monitor()->create();
        Note::factory()->for($other, 'owner')->create(['status' => 'accepted']);
        Note::factory()->for($other, 'owner')->create(['status' => 'pending']);

        $res = $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/'.$other->id)
            ->assertOk();

        $data = $res->json('data');
        $this->assertSame($other->id, $data['user']['id']);
        $this->assertSame($other->localized_name, $data['user']['name']);
        $this->assertArrayHasKey('avatar_url', $data['user']);
        $this->assertFalse($data['is_own']);
        $this->assertSame(2, $data['stats']['total']);
        $this->assertSame(1, $data['stats']['accepted']);
        $this->assertSame(1, $data['stats']['pending']);
    }

    #[Test]
    public function hides_drafts_from_strangers_but_shows_them_to_owner(): void
    {
        $viewer = User::factory()->monitor()->create();
        $other = User::factory()->monitor()->create();
        Note::factory()->for($other, 'owner')->create(['status' => 'draft']);
        Note::factory()->for($viewer, 'owner')->create(['status' => 'draft']);

        // الغريب لا يرى المسودة — تُستبعد من المجموع أيضاً (كمنطق الويب).
        $stranger = $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/'.$other->id)
            ->assertOk()
            ->json('data.stats');
        $this->assertSame(0, $stranger['total']);
        $this->assertSame(0, $stranger['draft']);

        // صاحب الملف يرى مسودته.
        $own = $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/'.$viewer->id)
            ->assertOk()
            ->json('data');
        $this->assertTrue($own['is_own']);
        $this->assertSame(1, $own['stats']['total']);
        $this->assertSame(1, $own['stats']['draft']);
    }

    #[Test]
    public function directories_include_avatar_url(): void
    {
        $viewer = User::factory()->monitor()->create();
        User::factory()->reportWriter()->create();

        $obs = $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/observers')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($obs);
        $this->assertArrayHasKey('avatar_url', $obs[0]);

        $writers = $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/writers')
            ->assertOk()
            ->json('data');
        $this->assertArrayHasKey('avatar_url', $writers[0]);
    }

    #[Test]
    public function unknown_user_returns_404(): void
    {
        $viewer = User::factory()->monitor()->create();

        $this->withHeaders($this->auth($viewer))
            ->getJson('/api/users/999999')
            ->assertNotFound();
    }

    #[Test]
    public function guests_cannot_open_profiles(): void
    {
        $other = User::factory()->monitor()->create();

        $this->getJson('/api/users/'.$other->id)->assertUnauthorized();
    }
}