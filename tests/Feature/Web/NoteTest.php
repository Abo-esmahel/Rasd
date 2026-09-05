<?php

namespace Tests\Feature\Web;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_can_create_draft(): void
    {
        $user = User::factory()->monitor()->create();

        $response = $this->actingAs($user)->post('/notes', [
            'floor_number' => 1,
            'camera_number' => 10,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'ملاحظة تجريبية',
            'action' => 'save',
        ]);

        $response->assertRedirect('/notes');
        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'status' => 'draft',
            'description' => 'ملاحظة تجريبية',
        ]);
    }

    public function test_monitor_can_create_and_send(): void
    {
        $user = User::factory()->monitor()->create();

        $response = $this->actingAs($user)->post('/notes', [
            'floor_number' => 2,
            'camera_number' => 5,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'ملاحظة مرسلة',
            'action' => 'send',
        ]);

        $response->assertRedirect('/notes');
        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_edit_own_note(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/notes/{$note->id}", [
            'floor_number' => 3,
            'camera_number' => 15,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف محدث للملاحظة يزيد عن عشرة أحرف',
        ]);

        $response->assertRedirect('/notes');
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'description' => 'وصف محدث للملاحظة يزيد عن عشرة أحرف',
        ]);
    }

    public function test_owner_can_delete_draft(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/notes/{$note->id}");

        $response->assertRedirect('/notes');
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_owner_can_send_draft(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/notes/{$note->id}/send");

        $response->assertRedirect('/notes');
        $note->refresh();
        $this->assertEquals('pending', $note->status);
    }

    public function test_report_writer_can_accept(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $response = $this->actingAs($writer)->post("/notes/{$note->id}/accept");

        $response->assertRedirect('/notes');
        $note->refresh();
        $this->assertEquals('accepted', $note->status);
    }

    public function test_report_writer_can_reject(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $response = $this->actingAs($writer)->post("/notes/{$note->id}/reject", [
            'rejection_reason' => 'سبب الرفض',
        ]);

        $response->assertRedirect('/notes');
        $note->refresh();
        $this->assertEquals('rejected', $note->status);
        $this->assertEquals('سبب الرفض', $note->rejection_reason);
    }

    public function test_owner_can_resend(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->rejected()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/notes/{$note->id}/resend");

        $response->assertRedirect('/notes');
        $note->refresh();
        $this->assertEquals('pending', $note->status);
    }
}
