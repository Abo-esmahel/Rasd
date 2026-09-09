<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * General-submission media: local disk only (submissions/{id}/{uuid}.ext).
 * Atomic, no silent failure. Accept copies media → new Note (notes/{id}/).
 */
class GeneralSubmissionMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
    }

    private function monitor(): User
    {
        return User::factory()->monitor()->create();
    }

    private function writer(): User
    {
        return User::factory()->reportWriter()->create();
    }

    private function payload(User $writer, array $extra = []): array
    {
        return array_merge([
            'floor_number' => 1,
            'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف إرسالية كافٍ لاجتياز التحقق المطلوب هنا',
            'report_writer_ids' => [$writer->id],
        ], $extra);
    }

    private function headers(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    private function jpeg(string $name = 'photo.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 't_');
        file_put_contents($tmp, hex2bin('FFD8FFE000104A46494600010100000100010000').str_repeat(chr(0), 300).hex2bin('FFD9'));
        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    private function mp4(string $name = 'video.mp4'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 't_');
        file_put_contents($tmp, hex2bin('00000018667479706D703432000000006D7034326D6F6F76').str_repeat(chr(0), 800));
        return new UploadedFile($tmp, $name, 'video/mp4', null, true);
    }

    private function wav(string $name = 'audio.wav'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 't_');
        $wav = 'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8).'data'.pack('V', 0);
        file_put_contents($tmp, $wav.str_repeat(chr(0), 800));
        return new UploadedFile($tmp, $name, 'audio/x-wav', null, true);
    }

    public function test_create_without_media(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions', $this->payload($writer), $this->headers());

        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 0)
            ->assertJsonPath('attachments_saved', 0);
        $this->assertDatabaseHas('general_submissions', ['id' => $res->json('submission_id'), 'status' => 'pending']);
    }

    public function test_create_with_single_image(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg('s.jpg')], 'client_files_count' => 1]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 1)
            ->assertJsonPath('attachments_saved', 1);

        $subId = $res->json('submission_id');
        $att = GeneralSubmissionAttachment::where('general_submission_id', $subId)->first();
        $this->assertNotNull($att);
        $this->assertStringStartsWith("submissions/{$subId}/", $att->file_path);
        Storage::disk('attachments')->assertExists($att->file_path);

        $view = $this->actingAs($monitor)->get("/submission-attachments/{$att->id}/view");
        $view->assertOk();
        $this->assertStringStartsWith('image/', $view->headers->get('Content-Type'));
        $this->assertEquals(Storage::disk('attachments')->get($att->file_path), file_get_contents($view->getFile()->getPathname()));
    }

    public function test_create_with_three_images(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg')], 'client_files_count' => 3]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('files_received', 3)->assertJsonPath('attachments_saved', 3);
        $this->assertEquals(3, GeneralSubmissionAttachment::where('general_submission_id', $res->json('submission_id'))->count());
    }

    public function test_image_video_audio(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg(), $this->mp4(), $this->wav()], 'client_files_count' => 3]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true);
        $atts = GeneralSubmissionAttachment::where('general_submission_id', $res->json('submission_id'))->get();
        $this->assertEquals(3, $atts->count());
        foreach ($atts as $a) {
            Storage::disk('attachments')->assertExists($a->file_path);
            $this->actingAs($monitor)->get("/submission-attachments/{$a->id}/view")->assertOk();
        }
    }

    public function test_full_reload_and_show_page(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg('keep.jpg')], 'client_files_count' => 1]),
            $this->headers());
        $subId = $res->json('submission_id');

        $fresh = GeneralSubmission::with('attachments')->find($subId);
        $this->assertEquals(1, $fresh->attachments->count());
        Storage::disk('attachments')->assertExists($fresh->attachments->first()->file_path);

        $this->actingAs($monitor)->get("/general-submissions/{$subId}")->assertOk()->assertSee('keep.jpg');
        $this->actingAs($monitor)->get("/general-submissions/{$subId}")->assertOk()->assertSee('keep.jpg');
    }

    public function test_storage_failure_rolls_back(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();
        $tmp = tempnam(sys_get_temp_dir(), 't_');
        file_put_contents($tmp, 'plain text must fail');
        $bad = new UploadedFile($tmp, 'note.txt', 'text/plain', null, true);

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg('ok.jpg'), $bad], 'client_files_count' => 2]),
            $this->headers());

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
        $this->assertEquals(0, GeneralSubmission::count());
        $this->assertEquals(0, GeneralSubmissionAttachment::count());
        $this->assertEquals([], Storage::disk('attachments')->allFiles('submissions'));
    }

    public function test_transport_loss_fails(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg()], 'client_files_count' => 2]),
            $this->headers());

        $res->assertStatus(422)->assertJsonPath('success', false);
        $this->assertEquals(0, GeneralSubmission::count());
    }

    public function test_accept_copies_media_to_note(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg('copy.jpg')], 'client_files_count' => 1]),
            $this->headers());
        $subId = $res->json('submission_id');
        $subAtt = GeneralSubmissionAttachment::where('general_submission_id', $subId)->first();
        $originalBytes = Storage::disk('attachments')->get($subAtt->file_path);

        $service = app(\App\Services\GeneralSubmissionService::class);
        $submission = GeneralSubmission::find($subId);
        $note = $service->accept($submission, $writer);

        $this->assertNotNull($note);
        $this->assertEquals(Note::STATUS_ACCEPTED, $note->status);
        $this->assertEquals(1, $note->attachments->count());
        $noteAtt = $note->attachments->first();
        $this->assertEquals('copy.jpg', $noteAtt->original_name);
        $this->assertStringStartsWith("notes/{$note->id}/", $noteAtt->file_path);
        Storage::disk('attachments')->assertExists($noteAtt->file_path);
        $this->assertEquals($originalBytes, Storage::disk('attachments')->get($noteAtt->file_path));
        // Submission source retained.
        Storage::disk('attachments')->assertExists($subAtt->file_path);
    }

    public function test_attachment_privacy(): void
    {
        $monitor = $this->monitor();
        $writer = $this->writer();
        $other = $this->monitor();

        $res = $this->actingAs($monitor)->post('/general-submissions',
            array_merge($this->payload($writer), ['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());
        $att = GeneralSubmissionAttachment::where('general_submission_id', $res->json('submission_id'))->first();

        $this->actingAs($other)->get("/submission-attachments/{$att->id}/view")->assertForbidden();
        $this->actingAs($monitor)->get("/submission-attachments/{$att->id}/view")->assertOk();
        $this->actingAs($writer)->get("/submission-attachments/{$att->id}/view")->assertOk();

        $this->actingAs($monitor)->get("/submission-attachments/{$att->id}/download")->assertForbidden();
        $this->actingAs($writer)->get("/submission-attachments/{$att->id}/download")->assertOk();
    }
}
