<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectionTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_rejection_requires_reason(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $response = $this->postJson("/api/notes/{$note->id}/reject", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_valid_rejection_succeeds(): void
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
        $note->refresh();
        $this->assertNotNull($note->processed_at);
    }

    public function test_rejection_stores_processor_and_timestamp(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($writer);

        $this->postJson("/api/notes/{$note->id}/reject", [
            'rejection_reason' => 'سبب الرفض',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $note->refresh();
        $this->assertEquals($writer->id, $note->processed_by);
        $this->assertNotNull($note->processed_at);
        $this->assertEquals('سبب الرفض', $note->rejection_reason);
    }
}
