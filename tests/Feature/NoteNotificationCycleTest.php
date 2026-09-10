<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteRejectedNotification;
use App\Notifications\NoteSentNotification;
use App\Services\NoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteNotificationCycleTest extends TestCase
{
    use RefreshDatabase;

    private NoteService $noteService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->noteService = app(NoteService::class);
    }

    
    public function test_second_rejection_after_resend_sends_new_notification_with_new_reason(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $note = $this->noteService->sendNote($monitor, $note);
        $this->noteService->rejectNote($writer, $note, 'السبب الأول للرفض');

        $this->assertCount(
            1,
            $monitor->notifications()->where('type', NoteRejectedNotification::class)->get()
        );

        
        $note = $this->noteService->resendRejectedNote($monitor, $note->fresh());
        $this->assertEquals(Note::STATUS_PENDING, $note->status);

        
        $this->noteService->rejectNote($writer, $note->fresh(), 'السبب الثاني للرفض');

        $rejections = $monitor->notifications()->where('type', NoteRejectedNotification::class)->get();
        $this->assertCount(2, $rejections, 'Second rejection after resend must send a new notification');
        $reasons = $rejections->map(fn ($n) => $n->data['reason'])->all();
        $this->assertEqualsCanonicalizing(['السبب الأول للرفض', 'السبب الثاني للرفض'], $reasons);
    }

    
    public function test_resend_notifies_writers_again(): void
    {
        $monitor = User::factory()->monitor()->create();
        $writer = User::factory()->reportWriter()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $note = $this->noteService->sendNote($monitor, $note);
        $this->assertEquals(
            1,
            $writer->notifications()->where('type', NoteSentNotification::class)->count()
        );

        $this->noteService->rejectNote($writer, $note, 'سبب الرفض');
        $this->noteService->resendRejectedNote($monitor, $note->fresh());

        $this->assertEquals(
            2,
            $writer->notifications()->where('type', NoteSentNotification::class)->count(),
            'Resend must notify writers again with a new pending-cycle notification'
        );
    }
}
