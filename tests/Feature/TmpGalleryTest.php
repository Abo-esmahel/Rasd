<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TmpGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_unifies_notes_and_submissions(): void
    {
        Storage::fake('attachments');
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'camera_number' => 3, 'status' => 'pending']);
        $img = Attachment::factory()->create(['note_id' => $note->id, 'mime_type' => 'image/jpeg']);
        Storage::disk('attachments')->put($img->file_path, 'x');

        $sub = GeneralSubmission::create([
            'user_id' => $monitor->id, 'floor_number' => 1, 'camera_number' => 7,
            'observed_at' => now(), 'description' => 'وصف إرسالية كاف للاختبار هنا',
            'status' => 'pending', 'sent_at' => now(),
        ]);
        $satt = GeneralSubmissionAttachment::create([
            'general_submission_id' => $sub->id, 'file_path' => 'submissions/1/a.jpg',
            'original_name' => 'a.jpg', 'mime_type' => 'video/mp4', 'file_size' => 100,
        ]);
        Storage::disk('attachments')->put($satt->file_path, 'x');

        // Guest redirected.
        $this->get('/gallery')->assertRedirect('/login');

        // Both sources listed with kind badges + parent links.
        $page = $this->actingAs($monitor)->get('/gallery')->assertOk();
        $page->assertSee('ملاحظة', false)->assertSee('إرسال عام', false);
        $page->assertSee('/notes/' . $note->id, false);
        $page->assertSee('/general-submissions/' . $sub->id, false);

        // Type filter keeps only matching source.
        $this->actingAs($monitor)->get('/gallery?type=video')->assertOk()
            ->assertSee('إرسال عام', false)->assertDontSee('ملاحظة #', false);

        // Camera filter.
        $this->actingAs($monitor)->get('/gallery?camera_number=7')->assertOk()
            ->assertSee('إرسال عام', false)->assertDontSee('كاميرا 3 ·', false);

        // Download gated: monitor no, writer yes.
        $page->assertDontSee('تنزيل', false);
        $writer = User::factory()->reportWriter()->create();
        $this->actingAs($writer)->get('/gallery')->assertOk()->assertSee('تنزيل', false);
    }
}
