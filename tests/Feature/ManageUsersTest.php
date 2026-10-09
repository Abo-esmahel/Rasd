<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * إدارة المستخدمين — للمدير العام وحده.
 *
 * تغطّي القيود التي تمنع النظام من فقدان مديره، وعدم المساس بكلمة السر
 * بعد الإنشاء: كلمة السر تُقبل مرة واحدة عند الإنشاء فقط.
 */
class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $u): string
    {
        return app(JwtService::class)->generateToken($u);
    }

    private function auth(User $u): array
    {
        return ['Authorization' => 'Bearer '.$this->token($u)];
    }

    private function gm(): User
    {
        return User::factory()->generalManager()->create();
    }

    #[Test]
    public function only_general_manager_can_manage_users(): void
    {
        $gm = $this->gm();
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $target = User::factory()->monitor()->create();

        $this->getJson('/api/managed-users', $this->auth($gm))->assertOk();

        foreach ([$monitor, $writer] as $user) {
            $h = $this->auth($user);
            $this->getJson('/api/managed-users', $h)->assertForbidden();
            $this->postJson('/api/managed-users', [
                'name' => 'x', 'username' => 'x'.uniqid(), 'password' => 'secret123', 'role' => 'monitor',
            ], $h)->assertForbidden();
            $this->putJson("/api/managed-users/{$target->id}", ['name' => 'hacked'], $h)->assertForbidden();
            $this->postJson("/api/managed-users/{$target->id}/ban", [], $h)->assertForbidden();
        }
    }

    #[Test]
    public function list_excludes_general_managers_and_hides_passwords(): void
    {
        $gm = $this->gm();
        User::factory()->monitor()->create(['username' => 'mon1']);
        User::factory()->reportWriter()->create(['username' => 'wr1']);

        $res = $this->getJson('/api/managed-users', $this->auth($gm))->assertOk();
        $usernames = array_column($res->json('data'), 'username');

        $this->assertContains('mon1', $usernames);
        $this->assertContains('wr1', $usernames);
        $this->assertNotContains($gm->username, $usernames, 'GM لا يدير GM');
        $this->assertStringNotContainsString('password', $res->getContent());
    }

    #[Test]
    public function search_and_role_filter_narrow_the_list(): void
    {
        $gm = $this->gm();
        User::factory()->monitor()->create(['username' => 'khalid99', 'name' => 'خالد']);
        User::factory()->reportWriter()->create(['username' => 'salma88', 'name' => 'سلمى']);

        $found = $this->getJson('/api/managed-users?q=khalid', $this->auth($gm))->assertOk();
        $this->assertCount(1, $found->json('data'));
        $this->assertSame('khalid99', $found->json('data.0.username'));

        $writers = $this->getJson('/api/managed-users?role=report_writer', $this->auth($gm))->assertOk();
        $this->assertCount(1, $writers->json('data'));
        $this->assertSame('salma88', $writers->json('data.0.username'));
    }

    #[Test]
    public function creating_a_user_hashes_the_password_and_forbids_gm_role(): void
    {
        $gm = $this->gm();

        $res = $this->postJson('/api/managed-users', [
            'name' => 'مراقب جديد',
            'username' => 'newmon',
            'password' => 'secret123',
            'role' => 'monitor',
        ], $this->auth($gm))->assertCreated();

        $id = $res->json('data.id');
        $created = User::find($id);

        $this->assertSame('monitor', $created->role);
        $this->assertTrue(Hash::check('secret123', $created->getAuthPassword()));
        $this->assertStringNotContainsString('secret123', $res->getContent());

        $this->postJson('/api/managed-users', [
            'name' => 'آخر مدير', 'username' => 'gm2', 'password' => 'secret123', 'role' => 'general_manager',
        ], $this->auth($gm))->assertStatus(422);
    }

    #[Test]
    public function username_must_be_unique(): void
    {
        $gm = $this->gm();
        User::factory()->monitor()->create(['username' => 'taken']);

        $this->postJson('/api/managed-users', [
            'name' => 'x', 'username' => 'taken', 'password' => 'secret123', 'role' => 'monitor',
        ], $this->auth($gm))->assertStatus(422);
    }

    #[Test]
    public function update_changes_fields_but_never_the_password(): void
    {
        $gm = $this->gm();
        $target = User::factory()->monitor()->create(['name' => 'الاسم القديم']);
        $hashBefore = $target->getAuthPassword();

        $res = $this->putJson("/api/managed-users/{$target->id}", [
            'name' => 'الاسم الجديد',
            'role' => 'report_writer',
            'password' => 'hacked999',
        ], $this->auth($gm))->assertOk();

        $after = $target->fresh();
        $this->assertSame('الاسم الجديد', $after->name);
        $this->assertSame('report_writer', $after->role);
        $this->assertSame($hashBefore, $after->getAuthPassword(), 'كلمة السر لا تتغير');
        $this->assertTrue(Hash::check('password', $after->getAuthPassword()));
        $this->assertFalse(Hash::check('hacked999', $after->getAuthPassword()));
        $this->assertStringNotContainsString('password', $res->getContent());
    }

    #[Test]
    public function ban_blocks_login_and_invalidates_existing_token(): void
    {
        $gm = $this->gm();
        $target = User::factory()->monitor()->create(['password' => 'secret123']);

        $this->postJson('/api/login', ['username' => $target->username, 'password' => 'secret123'])
            ->assertOk();
        $this->getJson('/api/me', $this->auth($target))->assertOk();

        $this->postJson("/api/managed-users/{$target->id}/ban", [
            'reason' => 'مخالفة',
        ], $this->auth($gm))->assertOk()->assertJsonPath('data.banned', true);

        $target->refresh();
        $this->assertTrue($target->isBanned());
        $this->assertSame('مخالفة', $target->ban_reason);

        $this->postJson('/api/login', ['username' => $target->username, 'password' => 'secret123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->getJson('/api/me', $this->auth($target))->assertStatus(401);

        $this->postJson("/api/managed-users/{$target->id}/unban", [], $this->auth($gm))
            ->assertOk()->assertJsonPath('data.banned', false);

        $this->postJson('/api/login', ['username' => $target->username, 'password' => 'secret123'])
            ->assertOk();
    }

    #[Test]
    public function manager_cannot_ban_or_edit_self_or_another_manager(): void
    {
        $gm = $this->gm();
        $otherGm = User::factory()->generalManager()->create();

        foreach ([$gm, $otherGm] as $target) {
            $this->postJson("/api/managed-users/{$target->id}/ban", [], $this->auth($gm))->assertForbidden();
            $this->putJson("/api/managed-users/{$target->id}", ['name' => 'x'], $this->auth($gm))
                ->assertForbidden();
        }

        $this->assertFalse($gm->fresh()->isBanned());
        $this->getJson('/api/managed-users', $this->auth($gm))->assertOk();
    }
}
