<?php

namespace Tests\Feature\Web;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesPageTempTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_index_loads(): void
    {
        $user = User::factory()->monitor()->create();
        Note::factory()->draft()->create(['user_id' => $user->id]);
        Note::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/notes');
        $response->assertOk();
    }

    public function test_notes_index_with_rejected_and_filters(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        Note::factory()->rejected()->create(['user_id' => $monitor->id, 'processed_by' => $writer->id]);
        Note::factory()->accepted()->create(['user_id' => $monitor->id, 'processed_by' => $writer->id]);

        $response = $this->actingAs($monitor)->get('/notes?status=rejected');
        $response->assertOk();

        $response = $this->actingAs($writer)->get('/notes');
        $response->assertOk();
    }

    public function test_my_notes_loads(): void
    {
        $user = User::factory()->monitor()->create();
        Note::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/my-notes');
        $response->assertOk();
    }
}
