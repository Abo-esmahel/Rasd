<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_owner_can_resend_rejected_note(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $note->refresh();
        $this->assertEquals('pending', $note->status);
    }

    public function test_others_cannot_resend(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $monitor1->id]);
        $token = $this->jwtService->generateToken($monitor2);

        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_states_fail(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_same_note_id_preserved(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $monitor->id]);
        $originalId = $note->id;
        $token = $this->jwtService->generateToken($monitor);

        $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $token",
        ]);

        $this->assertDatabaseHas('notes', [
            'id' => $originalId,
            'status' => 'pending',
        ]);
    }

    public function test_resend_clears_rejection_fields(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->rejected()->create([
            'user_id' => $monitor->id,
            'rejection_reason' => 'سبب الرفض',
            'processed_by' => $writer->id,
            'processed_at' => now(),
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
