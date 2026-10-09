<?php

namespace Tests\Feature;

use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الملف الشخصي للمستخدم — يجب فتحه بالضغط على اسمه/صورته في أي مكان.
 *
 * العطل الذي عولج: كانت الأسماء والصور في أغلب الشاشات مجرد نص، ولا يوجد
 * أي رابط للملف الشخصي. الآن كل اسم/صورة تشير إلى profile.showUser.
 */
class UserProfileLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_page_renders_for_another_user(): void
    {
        $viewer = User::factory()->monitor()->create();
        $other = User::factory()->monitor()->create();

        $this->actingAs($viewer)
            ->get(route('profile.showUser', $other->id))
            ->assertOk();
    }

    #[Test]
    public function notes_index_links_the_owner_name_and_avatar(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->for($monitor, 'owner')->create(['status' => 'pending']);

        $html = $this->actingAs($monitor)
            ->get(route('notes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('profile.showUser', $monitor->id),
            $html,
            'بطاقة الملاحظة يجب أن تحتوي رابطاً للملف الشخصي.'
        );
        $this->assertStringContainsString($monitor->localized_name, $html);
    }

    #[Test]
    public function my_notes_links_the_owner_name(): void
    {
        $monitor = User::factory()->monitor()->create();
        Note::factory()->for($monitor, 'owner')->create(['status' => 'pending']);

        $this->actingAs($monitor)
            ->get(route('notes.my'))
            ->assertOk()
            ->assertSee(route('profile.showUser', $monitor->id), false);
    }

    #[Test]
    public function note_show_links_the_owner_and_the_processor(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->for($monitor, 'owner')->create([
            'status' => 'rejected',
            'rejection_reason' => 'سبب',
            'processed_by' => $writer->id,
            'processed_at' => now(),
        ]);

        $html = $this->actingAs($writer)
            ->get(route('notes.show', $note))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('profile.showUser', $monitor->id), $html);
        $this->assertStringContainsString(route('profile.showUser', $writer->id), $html);
    }

    #[Test]
    public function reports_index_links_the_author(): void
    {
        $writer = User::factory()->reportWriter()->create();
        Report::factory()->for($writer, 'author')->create();

        $this->actingAs($writer)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('profile.showUser', $writer->id), false);
    }

    private function makeSubmission(User $owner, User $writer, array $attributes = []): GeneralSubmission
    {
        $submission = GeneralSubmission::create(array_merge([
            'user_id' => $owner->id,
            'floor_number' => 2,
            'camera_number' => 5,
            'observed_at' => now()->subHour(),
            'description' => 'وصف الطلب',
            'status' => 'pending',
        ], $attributes));

        $submission->reportWriters()->sync([$writer->id]);

        return $submission->fresh();
    }

    #[Test]
    public function general_submission_show_links_sender_and_processor(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $submission = $this->makeSubmission($monitor, $writer, [
            'status' => 'rejected',
            'rejection_reason' => 'سبب',
            'processed_by' => $writer->id,
            'processed_at' => now(),
        ]);

        $html = $this->actingAs($writer)
            ->get(route('general-submissions.show', $submission))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('profile.showUser', $monitor->id), $html);
        $this->assertStringContainsString(route('profile.showUser', $writer->id), $html);
    }

    #[Test]
    public function general_submissions_index_links_the_sender(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $this->makeSubmission($monitor, $writer);

        $this->actingAs($writer)
            ->get(route('general-submissions.index'))
            ->assertOk()
            ->assertSee(route('profile.showUser', $monitor->id), false);
    }

    #[Test]
    public function self_link_points_at_the_own_profile_route(): void
    {
        $monitor = User::factory()->monitor()->create();
        Note::factory()->for($monitor, 'owner')->create(['status' => 'pending']);

        $html = $this->actingAs($monitor)
            ->get(route('notes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('profile.show'), $html);
    }
}