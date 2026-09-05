<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_owner_can_edit_own_pending_note(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'description' => 'ملاحظة محدثة',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
    }

    public function test_other_monitor_cannot_edit_pending_note(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor1->id]);
        $token = $this->jwtService->generateToken($monitor2);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'description' => 'ملاحظة محدثة',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_drafts(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->deleteJson("/api/notes/{$note->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertNoContent();
    }

    public function test_only_owner_can_delete_drafts(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor1->id]);
        $token = $this->jwtService->generateToken($monitor2);

        $response = $this->deleteJson("/api/notes/{$note->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_only_report_writer_can_accept_notes(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $monitorToken = $this->jwtService->generateToken($monitor);
        $response = $this->postJson("/api/notes/{$note->id}/accept", [], [
            'Authorization' => "Bearer $monitorToken",
        ]);
        $response->assertForbidden();

        $writerToken = $this->jwtService->generateToken($writer);
        $response = $this->postJson("/api/notes/{$note->id}/accept", [], [
            'Authorization' => "Bearer $writerToken",
        ]);
        $response->assertOk();
    }

    public function test_only_owner_can_resend_notes(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $monitor1->id]);

        $monitor2Token = $this->jwtService->generateToken($monitor2);
        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $monitor2Token",
        ]);
        $response->assertForbidden();

        $monitor1Token = $this->jwtService->generateToken($monitor1);
        $response = $this->postJson("/api/notes/{$note->id}/resend", [], [
            'Authorization' => "Bearer $monitor1Token",
        ]);
        $response->assertOk();
    }

    public function test_monitor_cannot_accept_notes(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/accept", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_monitor_cannot_reject_notes(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $response = $this->postJson("/api/notes/{$note->id}/reject", [
            'rejection_reason' => 'سبب الرفض',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }
}
