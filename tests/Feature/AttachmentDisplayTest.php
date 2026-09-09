<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DISPLAY PIPELINE regression test — covers *viewing* the attachment,
 * not just uploading it.
 *
 * Attachment DB record → Note relationship → GET /notes/{id} →
 * Blade show → <img src="view-route"> → 302 → Cloudinary secure_url.
 */
class AttachmentDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_renders_attachment_with_inline_image(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'status' => 'draft']);
        $attachment = Attachment::factory()->create([
            'note_id' => $note->id,
            'original_name' => 'test-real.jpg',
            'mime_type' => 'image/jpeg',
            'file_path' => 'notes/'.$note->id.'/test-uuid-123',
            'cloudinary_resource_type' => 'image',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/notes/sample.jpg',
            'file_size' => 12345,
        ]);

        // 1. DB record exists
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'note_id' => $note->id,
            'original_name' => 'test-real.jpg',
        ]);

        // 2. Relationship returns it
        $this->assertCount(1, $note->fresh()->load('attachments')->attachments);

        // 3. Show page renders it with a visible inline <img>
        $response = $this->actingAs($monitor)->get("/notes/{$note->id}");
        $response->assertOk();
        $response->assertSee('test-real.jpg');
        $response->assertSee('/attachments/'.$attachment->id.'/view', false);
        // inline visible image (DISPLAY PIPELINE: no click needed)
        $response->assertSee('data-testid="attachment-image"', false);
        // viewer modal + JS must exist, otherwise the عرض button is dead
        $response->assertSee('attachment-view-modal', false);
        $response->assertSee('function openAttachmentView', false);
    }

    public function test_view_endpoint_redirects_to_cloudinary_url(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'status' => 'draft']);
        $attachment = Attachment::factory()->create([
            'note_id' => $note->id,
            'mime_type' => 'image/jpeg',
            'cloudinary_resource_type' => 'image',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/notes/sample.jpg',
        ]);

        $response = $this->actingAs($monitor)->get("/attachments/{$attachment->id}/view");
        $response->assertRedirect('https://res.cloudinary.com/demo/image/upload/v1/notes/sample.jpg');
    }

    public function test_show_page_without_attachments_has_no_image(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'status' => 'draft']);

        $response = $this->actingAs($monitor)->get("/notes/{$note->id}");
        $response->assertOk();
        $response->assertDontSee('data-testid="attachment-image"', false);
    }
}
