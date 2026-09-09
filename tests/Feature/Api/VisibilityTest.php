<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_monitor_cannot_see_other_monitors_draft(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $draft = Note::factory()->draft()->create(['user_id' => $monitor2->id]);

        $token = $this->jwtService->generateToken($monitor1);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_monitor_can_see_other_monitors_pending_notes(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $pending = Note::factory()->pending()->create(['user_id' => $monitor2->id]);

        $token = $this->jwtService->generateToken($monitor1);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($pending->id, $ids);
    }

    public function test_monitor_can_see_other_monitors_accepted_notes(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $accepted = Note::factory()->accepted()->create(['user_id' => $monitor2->id]);

        $token = $this->jwtService->generateToken($monitor1);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($accepted->id, $ids);
    }

    public function test_monitor_can_see_other_monitors_rejected_notes(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $rejected = Note::factory()->rejected()->create(['user_id' => $monitor2->id]);

        $token = $this->jwtService->generateToken($monitor1);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($rejected->id, $ids);
    }

    public function test_monitor_can_see_own_drafts(): void
    {
        $monitor = User::factory()->monitor()->create();
        $draft = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $token = $this->jwtService->generateToken($monitor);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($draft->id, $ids);
    }

    public function test_report_writer_cannot_see_drafts(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $monitor = User::factory()->monitor()->create();
        $draft = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $token = $this->jwtService->generateToken($writer);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_report_writer_can_see_pending_notes(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $monitor = User::factory()->monitor()->create();
        $pending = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $token = $this->jwtService->generateToken($writer);
        $response = $this->getJson('/api/notes', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($pending->id, $ids);
    }
}
