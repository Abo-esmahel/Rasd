<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_draft_to_pending(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/send", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'status' => 'pending',
        ]);
    }

    public function test_pending_to_accepted(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $response = $this->postJson("/api/notes/{$note->id}/accept", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'status' => 'accepted',
            'processed_by' => $writer->id,
        ]);
    }

    public function test_pending_to_rejected(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $response = $this->postJson("/api/notes/{$note->id}/reject", [
            'rejection_reason' => 'المعلومات غير كافية',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'status' => 'rejected',
            'rejection_reason' => 'المعلومات غير كافية',
            'processed_by' => $writer->id,
        ]);
    }

    public function test_rejected_to_pending(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'status' => 'pending',
            'rejection_reason' => null,
            'processed_by' => null,
        ]);
    }

    public function test_cannot_send_non_draft(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/send", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_accept_non_pending(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $response = $this->postJson("/api/notes/{$note->id}/accept", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_reject_non_pending(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $response = $this->postJson("/api/notes/{$note->id}/reject", [
            'rejection_reason' => 'سبب',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_resend_non_rejected(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_delete_non_draft(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->deleteJson("/api/notes/{$note->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_accepted_note_cannot_be_edited(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->accepted()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'description' => 'تحديث',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_send_sets_sent_at(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $this->postJson("/api/notes/{$note->id}/send", [], [
            'Authorization' => "Bearer $token",
        ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'status' => 'pending',
        ]);
        $note->refresh();
        $this->assertNotNull($note->sent_at);
    }

    public function test_reject_clears_rejection_on_resend(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->rejected()->create([
            'user_id' => $monitor->id,
            'rejection_reason' => 'سبب الرفض',
            'processed_by' => $writer->id,
        ]);

        $token = $this->jwtService->generateToken($monitor);
        $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $note->refresh();
        $this->assertNull($note->rejection_reason);
        $this->assertNull($note->processed_by);
        $this->assertNull($note->processed_at);
        $this->assertNotNull($note->sent_at);
    }
}
