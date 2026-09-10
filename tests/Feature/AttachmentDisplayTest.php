<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            'file_size' => 12345,
        ]);

        
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'note_id' => $note->id,
            'original_name' => 'test-real.jpg',
        ]);

        
        $this->assertCount(1, $note->fresh()->load('attachments')->attachments);

        
        $response = $this->actingAs($monitor)->get("/notes/{$note->id}");
        $response->assertOk();
        $response->assertSee('test-real.jpg');
        $response->assertSee('/attachments/'.$attachment->id.'/view', false);
        
        $response->assertSee('data-testid="attachment-image"', false);
        
        $response->assertSee('attachment-view-modal', false);
        $response->assertSee('function openAttachmentView', false);
    }

    public function test_view_endpoint_returns_404_for_missing_local_file(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'status' => 'draft']);
        $attachment = Attachment::factory()->create([
            'note_id' => $note->id,
            'mime_type' => 'image/jpeg',
            'file_path' => 'notes/'.$note->id.'/missing-file',
        ]);

        $response = $this->actingAs($monitor)->get("/attachments/{$attachment->id}/view");
        $response->assertNotFound();
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
