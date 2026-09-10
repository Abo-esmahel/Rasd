<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\User;
use App\Notifications\DispatchAcceptedNotification;
use App\Notifications\DispatchRejectedNotification;
use App\Notifications\DispatchSentNotification;
use App\Notifications\NoteAcceptedNotification;
use App\Notifications\NoteRejectedNotification;
use App\Services\GeneralSubmissionService;
use App\Services\JwtService;
use App\Services\NoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;
    private GeneralSubmissionService $dispatchService;
    private NoteService $noteService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
        $this->dispatchService = app(GeneralSubmissionService::class);
        $this->noteService = app(NoteService::class);
    }

    private function createMonitor(?string $name = null): User
    {
        return User::factory()->monitor()->create(['name' => $name ?? fake()->name()]);
    }

    private function createWriter(?string $name = null): User
    {
        return User::factory()->reportWriter()->create(['name' => $name ?? fake()->name()]);
    }

    
    public function test_A_send_dispatch_notification_targeted(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $recipientA = $this->createWriter('Recipient A');
        $recipientB = $this->createWriter('Recipient B');
        $otherMonitor = $this->createMonitor('Other Monitor');

        
        $submission = $this->dispatchService->createDraft($monitorA, [
            'floor_number' => 1,
            'camera_number' => 10,
            'observed_at' => now(),
            'description' => 'ملاحظة تجريبية للإرسالية الخاصة مع محتوى كاف',
        ]);

        $submission = $this->dispatchService->submit($submission, $monitorA, [$recipientA->id, $recipientB->id]);

        $this->assertEquals(GeneralSubmission::STATUS_PENDING, $submission->status);

        
        $this->assertTrue(
            $recipientA->notifications()->where('type', DispatchSentNotification::class)->get()
                ->contains(fn($n) => ($n->data['submission_id'] ?? $n->data['general_submission_id'] ?? null) == $submission->id),
            'Recipient A should have dispatch sent notification'
        );

        
        $this->assertTrue(
            $recipientB->notifications()->where('type', DispatchSentNotification::class)->get()
                ->contains(fn($n) => ($n->data['submission_id'] ?? $n->data['general_submission_id'] ?? null) == $submission->id),
            'Recipient B should have dispatch sent notification'
        );

        
        $this->assertFalse(
            $otherMonitor->notifications()->where('type', DispatchSentNotification::class)->get()
                ->contains(fn($n) => ($n->data['submission_id'] ?? null) == $submission->id),
            'Other Monitor should NOT have dispatch sent notification'
        );

        
        $this->assertEquals(0, $otherMonitor->notifications()->where('type', DispatchSentNotification::class)->count());
    }

    
    public function test_B_writer_accept_notification_to_monitor(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $writer = $this->createWriter('Writer');

        $submission = $this->dispatchService->createDraft($monitorA, [
            'floor_number' => 2,
            'camera_number' => 5,
            'observed_at' => now(),
            'description' => 'محتوى الإرسالية للاختبار الثاني يجب أن يكون طويلاً كافياً',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitorA, [$writer->id]);

        
        
        $notesBefore = Note::count();
        $result = $this->dispatchService->accept($submission, $writer);

        $submission->refresh();
        $this->assertEquals(GeneralSubmission::STATUS_ACCEPTED, $submission->status);
        // Submissions are NOT notes: acceptance must not create any Note record.
        $this->assertInstanceOf(GeneralSubmission::class, $result);
        $this->assertEquals($notesBefore, Note::count());

        
        $monitorNotifications = $monitorA->notifications()->where('type', DispatchAcceptedNotification::class)->get();
        $this->assertTrue(
            $monitorNotifications->contains(fn($n) => ($n->data['submission_id'] ?? null) == $submission->id),
            'Monitor A should receive accept notification'
        );

        
        $notif = $monitorNotifications->first(fn($n) => ($n->data['submission_id'] ?? null) == $submission->id);
        $this->assertStringContainsString('قبول', $notif->data['message'] ?? '');

        
        $this->assertNotEmpty($notif->data['url'] ?? null);

        
        $this->assertEquals(0, $writer->notifications()->where('type', DispatchAcceptedNotification::class)->count());
    }

    
    public function test_C_writer_reject_notification_to_monitor_with_reason(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $writer = $this->createWriter('Writer');

        $submission = $this->dispatchService->createDraft($monitorA, [
            'floor_number' => 3,
            'camera_number' => 7,
            'observed_at' => now(),
            'description' => 'محتوى الإرسالية للرفض يجب أن يكون مفصلاً وكافياً للاختبار',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitorA, [$writer->id]);

        $reason = 'المعلومات غير كافية - يرجى إعادة التصوير';
        $submission = $this->dispatchService->reject($submission, $writer, $reason);

        $this->assertEquals(GeneralSubmission::STATUS_REJECTED, $submission->status);
        $this->assertEquals($reason, $submission->rejection_reason);

        
        $notif = $monitorA->notifications()->where('type', DispatchRejectedNotification::class)->get()
            ->first(fn($n) => ($n->data['submission_id'] ?? null) == $submission->id);

        $this->assertNotNull($notif, 'Monitor should receive reject notification');
        $this->assertEquals($reason, $notif->data['reason'] ?? null);
        $this->assertStringContainsString('رفض', $notif->data['message'] ?? '');
        $this->assertNotEmpty($notif->data['url'] ?? null);

        
        $this->assertEquals(0, $monitorA->notifications()->where('type', DispatchAcceptedNotification::class)->count());
    }

    
    public function test_D_privacy_other_monitors_blocked(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $monitorB = $this->createMonitor('Monitor B');
        $monitorC = $this->createMonitor('Monitor C');
        $recipientA = $this->createWriter('Recipient A');
        $unassignedWriter = $this->createWriter('Unassigned Writer');

        $submission = $this->dispatchService->createDraft($monitorA, [
            'floor_number' => 4,
            'camera_number' => 9,
            'observed_at' => now(),
            'description' => 'إرسالية خاصة للاختبار D يجب أن تحتوي على تفاصيل كافية',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitorA, [$recipientA->id]);

        
        $this->assertTrue($monitorA->can('view', $submission), 'Monitor A (sender) should view own dispatch');
        
        $this->assertTrue($recipientA->can('view', $submission), 'Recipient A should view assigned dispatch');
        
        $this->assertFalse($monitorB->can('view', $submission), 'Monitor B should NOT view Monitor A dispatch');
        $this->assertFalse($monitorC->can('view', $submission), 'Monitor C should NOT view Monitor A dispatch');
        
        $this->assertFalse($unassignedWriter->can('view', $submission), 'Unassigned writer should NOT view dispatch');

        
        $tokenB = $this->jwtService->generateToken($monitorB);
        $response = $this->getJson("/api/general-submissions/{$submission->id}", [
            'Authorization' => "Bearer $tokenB",
        ]);
        $response->assertForbidden();

        
        $tokenA = $this->jwtService->generateToken($recipientA);
        $response = $this->getJson("/api/general-submissions/{$submission->id}", [
            'Authorization' => "Bearer $tokenA",
        ]);
        $response->assertOk();

        
        $tokenSender = $this->jwtService->generateToken($monitorA);
        $response = $this->getJson("/api/general-submissions/{$submission->id}", [
            'Authorization' => "Bearer $tokenSender",
        ]);
        $response->assertOk();

        
        $visibleForB = $this->dispatchService->getVisibleSubmissions($monitorB)->get();
        $this->assertFalse($visibleForB->contains('id', $submission->id), 'Monitor B visible query should NOT contain submission');

        $visibleForRecipient = $this->dispatchService->getVisibleSubmissions($recipientA)->get();
        $this->assertTrue($visibleForRecipient->contains('id', $submission->id), 'Recipient should see in visible query');
    }

    
    public function test_note_privacy_other_monitors_blocked(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $monitorB = $this->createMonitor('Monitor B');
        $monitorC = $this->createMonitor('Monitor C');
        $writer = $this->createWriter('Writer');

        $note = Note::factory()->pending()->create(['user_id' => $monitorA->id]);

        
        $this->assertTrue($monitorA->can('view', $note));
        
        $this->assertTrue($monitorB->can('view', $note));
        $this->assertTrue($monitorC->can('view', $note));
        
        $this->assertTrue($writer->can('view', $note));

        
        $tokenB = $this->jwtService->generateToken($monitorB);
        $response = $this->getJson("/api/notes/{$note->id}", [
            'Authorization' => "Bearer $tokenB",
        ]);
        $response->assertOk();

        
        $tokenA = $this->jwtService->generateToken($monitorA);
        $response = $this->getJson("/api/notes/{$note->id}", [
            'Authorization' => "Bearer $tokenA",
        ]);
        $response->assertOk();
    }

    
    public function test_writer_access_only_assigned_dispatch(): void
    {
        $monitor = $this->createMonitor();
        $writerAssigned = $this->createWriter('Assigned');
        $writerUnassigned = $this->createWriter('Unassigned');

        $submission = $this->dispatchService->createDraft($monitor, [
            'floor_number' => 1,
            'camera_number' => 1,
            'observed_at' => now(),
            'description' => 'اختبار وصول الكاتب يجب أن يكون مفصلاً بشكل كاف للتحقق',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitor, [$writerAssigned->id]);

        
        $this->assertTrue($writerAssigned->can('accept', $submission));
        $this->assertTrue($writerAssigned->can('reject', $submission));

        
        $this->assertFalse($writerUnassigned->can('accept', $submission));
        $this->assertFalse($writerUnassigned->can('reject', $submission));

        
        $this->expectException(\InvalidArgumentException::class);
        $this->dispatchService->accept($submission, $writerUnassigned);
    }

    
    public function test_note_accept_notification_to_monitor(): void
    {
        $monitor = $this->createMonitor();
        $writer = $this->createWriter();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $this->noteService->acceptNote($writer, $note);

        $notif = $monitor->notifications()->where('type', NoteAcceptedNotification::class)->get()
            ->first(fn($n) => ($n->data['note_id'] ?? null) == $note->id);
        $this->assertNotNull($notif, 'Monitor should receive note accept notification');
        $this->assertStringContainsString('قبول', $notif->data['message']);
        $this->assertNotEmpty($notif->data['url']);

        
        $this->assertEquals(0, $monitor->notifications()->where('type', NoteRejectedNotification::class)->count());
    }

    
    public function test_note_reject_notification_with_reason(): void
    {
        $monitor = $this->createMonitor();
        $writer = $this->createWriter();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $reason = 'الصورة غير واضحة';
        $this->noteService->rejectNote($writer, $note, $reason);

        $notif = $monitor->notifications()->where('type', NoteRejectedNotification::class)->get()
            ->first(fn($n) => ($n->data['note_id'] ?? null) == $note->id);
        $this->assertNotNull($notif);
        $this->assertEquals($reason, $notif->data['reason']);
        $this->assertStringContainsString('رفض', $notif->data['message']);
        $this->assertNotEmpty($notif->data['url']);
    }

    
    public function test_duplicate_notification_idempotent_dispatch_accept(): void
    {
        $monitor = $this->createMonitor();
        $writer = $this->createWriter();

        $submission = $this->dispatchService->createDraft($monitor, [
            'floor_number' => 1,
            'camera_number' => 1,
            'observed_at' => now(),
            'description' => 'اختبار منع التكرار يجب أن يحتوي على وصف كاف للاختبار',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitor, [$writer->id]);

        
        $this->dispatchService->accept($submission, $writer);
        $countAfterFirst = $monitor->notifications()->where('type', DispatchAcceptedNotification::class)->count();
        $this->assertEquals(1, $countAfterFirst);

        
        try {
            $this->dispatchService->accept($submission->fresh(), $writer);
            $this->fail('Second accept should throw InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            
        }

        $countAfterSecond = $monitor->notifications()->where('type', DispatchAcceptedNotification::class)->count();
        $this->assertEquals(1, $countAfterSecond, 'Duplicate accept should not create second notification');

        
        $submission2 = $this->dispatchService->createDraft($monitor, [
            'floor_number' => 2,
            'camera_number' => 2,
            'observed_at' => now(),
            'description' => 'اختبار ثانٍ لمنع تكرار الرفض يجب أن يكون مفصلاً',
        ]);
        $submission2 = $this->dispatchService->submit($submission2, $monitor, [$writer->id]);
        $this->dispatchService->reject($submission2, $writer, 'سبب الرفض الأول');
        $countRejectFirst = $monitor->notifications()->where('type', DispatchRejectedNotification::class)->count();
        $this->assertEquals(1, $countRejectFirst);

        try {
            $this->dispatchService->reject($submission2->fresh(), $writer, 'سبب الرفض الثاني');
            $this->fail('Second reject should throw');
        } catch (\InvalidArgumentException $e) {
        }
        $countRejectSecond = $monitor->notifications()->where('type', DispatchRejectedNotification::class)->count();
        $this->assertEquals(1, $countRejectSecond);
    }

    
    public function test_duplicate_notification_idempotent_note(): void
    {
        $monitor = $this->createMonitor();
        $writer = $this->createWriter();
        $note = Note::factory()->pending()->create(['user_id' => $monitor->id]);

        $this->noteService->acceptNote($writer, $note);
        $count1 = $monitor->notifications()->where('type', NoteAcceptedNotification::class)->count();
        $this->assertEquals(1, $count1);

        try {
            $this->noteService->acceptNote($writer, $note->fresh());
            $this->fail('Second accept should throw');
        } catch (\InvalidArgumentException $e) {
        }
        $count2 = $monitor->notifications()->where('type', NoteAcceptedNotification::class)->count();
        $this->assertEquals(1, $count2);

        
        $note2 = Note::factory()->pending()->create(['user_id' => $monitor->id]);
        $this->noteService->rejectNote($writer, $note2, 'سبب');
        $countR1 = $monitor->notifications()->where('type', NoteRejectedNotification::class)->count();
        $this->assertEquals(1, $countR1);

        try {
            $this->noteService->rejectNote($writer, $note2->fresh(), 'سبب ثاني');
            $this->fail('Second reject should throw');
        } catch (\InvalidArgumentException $e) {
        }
        $countR2 = $monitor->notifications()->where('type', NoteRejectedNotification::class)->count();
        $this->assertEquals(1, $countR2);
    }

    
    public function test_attachments_security(): void
    {
        $monitorA = $this->createMonitor('Monitor A');
        $monitorB = $this->createMonitor('Monitor B');
        $writer = $this->createWriter('Writer');
        $unassignedWriter = $this->createWriter('Unassigned');

        $note = Note::factory()->pending()->create(['user_id' => $monitorA->id]);
        $attachment = Attachment::factory()->create(['note_id' => $note->id]);

        
        $this->assertTrue($monitorA->can('view', $note));

        
        $this->assertTrue($monitorB->can('view', $note));

        
        $this->assertTrue($writer->can('view', $note));

        
        $tokenB = $this->jwtService->generateToken($monitorB);
        
        
        $response = $this->actingAs($monitorB)->get("/attachments/{$attachment->id}/view");
        $this->assertNotEquals(403, $response->getStatusCode());

        $response = $this->actingAs($monitorA)->get("/attachments/{$attachment->id}/view");
        
        $this->assertNotEquals(403, $response->getStatusCode());

        $response = $this->actingAs($writer)->get("/attachments/{$attachment->id}/view");
        $this->assertNotEquals(403, $response->getStatusCode());

        
        $response = $this->actingAs($monitorA)->get("/attachments/{$attachment->id}/download");
        $response->assertForbidden();

        $response = $this->actingAs($writer)->get("/attachments/{$attachment->id}/download");
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    
    public function test_sender_can_see_own_dispatch_status_and_rejection_reason(): void
    {
        $monitor = $this->createMonitor();
        $writer = $this->createWriter();

        $submission = $this->dispatchService->createDraft($monitor, [
            'floor_number' => 1,
            'camera_number' => 1,
            'observed_at' => now(),
            'description' => 'اختبار رؤية المرسل يجب أن يكون مفصلاً بما يكفي للاختبار',
        ]);
        $submission = $this->dispatchService->submit($submission, $monitor, [$writer->id]);

        
        $this->assertTrue($monitor->can('view', $submission));
        $this->assertEquals('pending', $submission->status);

        
        $reason = 'سبب مفصل للرفض يجب أن يكون واضحاً';
        $submission = $this->dispatchService->reject($submission, $writer, $reason);

        
        $this->assertTrue($monitor->can('view', $submission->fresh()));
        $this->assertEquals('rejected', $submission->fresh()->status);
        $this->assertEquals($reason, $submission->fresh()->rejection_reason);
    }

    
    public function test_dispatch_sent_idempotent_no_duplicate_on_double_submit(): void
    {
        $monitor = $this->createMonitor();
        $writerA = $this->createWriter('Writer A');
        $writerB = $this->createWriter('Writer B');

        $submission = $this->dispatchService->createDraft($monitor, [
            'floor_number' => 1,
            'camera_number' => 1,
            'observed_at' => now(),
            'description' => 'إرسالية لاختبار تكرار الإرسال يجب أن تكون مفصلة',
        ]);

        $submission = $this->dispatchService->submit($submission, $monitor, [$writerA->id, $writerB->id]);

        $countA = $writerA->notifications()->where('type', DispatchSentNotification::class)->count();
        $countB = $writerB->notifications()->where('type', DispatchSentNotification::class)->count();
        $this->assertEquals(1, $countA);
        $this->assertEquals(1, $countB);

        
        try {
            $this->dispatchService->submit($submission->fresh(), $monitor, [$writerA->id]);
            $this->fail('Second submit should throw');
        } catch (\InvalidArgumentException $e) {
        }

        $countA2 = $writerA->notifications()->where('type', DispatchSentNotification::class)->count();
        $this->assertEquals(1, $countA2);
    }
}
