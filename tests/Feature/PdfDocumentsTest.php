<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\PdfDocument;
use App\Models\Report;
use App\Models\User;
use App\Services\JwtService;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مستندات PDF على السيرفر — توليد حقيقي عبر Chrome headless (بدون محاكاة).
 */
class PdfDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        Storage::fake('local');
    }

    private function token(User $user): string
    {
        return app(JwtService::class)->generateToken($user);
    }

    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user)];
    }

    #[Test]
    public function note_pdf_generated_on_create_and_matches_content(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'وصف تجريبي للطباعة']);

        $row = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)
            ->where('locale', 'ar')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(PdfDocument::STATUS_READY, $row->status);
        $this->assertTrue($row->fileExists());
        $bytes = Storage::disk('local')->get($row->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);

        $hash = app(PdfDocumentService::class)->contentHash($note->fresh(), 'ar');
        $this->assertSame($hash, $row->content_hash);
    }

    #[Test]
    public function note_pdf_regenerates_on_update_and_prunes_old_file(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();

        $old = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->firstOrFail();
        $oldPath = $old->file_path;
        $this->assertTrue(Storage::disk('local')->exists($oldPath));

        $note->update(['description' => 'وصف محدّث تماماً']);

        $new = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->firstOrFail();
        $this->assertSame(PdfDocument::STATUS_READY, $new->status);
        $this->assertNotSame($old->content_hash, $new->content_hash);
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue($new->fileExists());
    }

    #[Test]
    public function note_pdf_embeds_images_and_survives_missing_and_corrupt_files(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();

        $img = imagecreatetruecolor(900, 700);
        imagefill($img, 0, 0, imagecolorallocate($img, 14, 106, 56));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('attachments')->put('t/ok.png', $png);
        $note->attachments()->create([
            'file_path' => 't/ok.png', 'original_name' => 'صورة.png',
            'mime_type' => 'image/png', 'file_size' => strlen($png),
        ]);

        $note->attachments()->create([
            'file_path' => 't/gone.jpg', 'original_name' => 'مفقودة.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1234,
        ]);
        Storage::disk('attachments')->put('t/bad.jpg', 'not-an-image-at-all');
        $note->attachments()->create([
            'file_path' => 't/bad.jpg', 'original_name' => 'تالفة.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 20,
        ]);

        $svc = app(PdfDocumentService::class);
        $row = $svc->resolve($note->fresh(), 'ar');

        $this->assertSame(PdfDocument::STATUS_READY, $row->status);
        $bytes = Storage::disk('local')->get($row->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(20000, strlen($bytes));
    }

    #[Test]
    public function video_only_note_gets_pdf_without_raw_video(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();

        $src = tempnam(sys_get_temp_dir(), 'vsrc');
        $mp4 = $src.'.mp4';
        $r = \Illuminate\Support\Facades\Process::timeout(30)->run([
            'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=5',
            '-pix_fmt', 'yuv420p', $mp4,
        ]);
        if (! $r->successful() || ! is_file($mp4)) {
            $this->markTestSkipped('ffmpeg video synthesis failed');
        }
        Storage::disk('attachments')->put('t/v.mp4', (string) file_get_contents($mp4));
        @unlink($src);
        @unlink($mp4);
        $note->attachments()->create([
            'file_path' => 't/v.mp4', 'original_name' => 'فيديو.mp4',
            'mime_type' => 'video/mp4', 'file_size' => Storage::disk('attachments')->size('t/v.mp4'),
        ]);

        $row = app(PdfDocumentService::class)->resolve($note->fresh(), 'ar');
        $this->assertSame(PdfDocument::STATUS_READY, $row->status);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($row->file_path));
    }

    #[Test]
    public function pdf_endpoint_requires_permission_and_serves_bytes(): void
    {
        $owner = User::factory()->monitor()->create();
        $stranger = User::factory()->monitor()->create();
        $note = Note::factory()->for($owner, 'owner')->create(['status' => Note::STATUS_DRAFT]);

        $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($stranger))
            ->assertStatus(403);

        $res = $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($owner))
            ->assertStatus(200);
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
    }

    #[Test]
    public function status_endpoint_never_leaves_the_client_stuck_for_a_new_locale(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();

        $this->assertSame(0, PdfDocument::where('locale', 'en')
            ->where('documentable_type', Note::class)->where('documentable_id', $note->id)->count());

        $this->getJson("/api/notes/{$note->id}/pdf-status?locale=en", $this->auth($user))
            ->assertStatus(200)
            ->assertJson(['success' => true, 'ready' => true]);

        $this->assertSame(1, PdfDocument::where('locale', 'en')
            ->where('documentable_type', Note::class)->where('documentable_id', $note->id)->count());

        $bytes = $this->getJson("/api/notes/{$note->id}/pdf?locale=en", $this->auth($user))
            ->assertStatus(200);
        $this->assertStringStartsWith('%PDF', (string) $bytes->getContent());
    }

    #[Test]
    public function status_endpoint_reports_unavailable_export_as_422(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $report = Report::factory()->for($writer, 'author')->create(['status' => 'draft']);

        $this->getJson("/api/reports/{$report->id}/pdf-status?locale=ar", $this->auth($writer))
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function status_endpoint_respects_permissions(): void
    {
        $owner = User::factory()->monitor()->create();
        $stranger = User::factory()->monitor()->create();
        $note = Note::factory()->for($owner, 'owner')->create();

        $this->getJson("/api/notes/{$note->id}/pdf-status", $this->auth($stranger))
            ->assertStatus(403);
    }

    #[Test]
    public function description_is_split_into_justified_paragraphs(): void
    {
        $svc = app(PdfDocumentService::class);

        $paragraphs = $svc->descriptionParagraphs(
            "السطر الأول من الملاحظة.\n\nالسطر الثاني بعد فاصل فارغ.",
            'ar'
        );
        $this->assertSame([
            'السطر الأول من الملاحظة.',
            'السطر الثاني بعد فاصل فارغ.',
        ], $paragraphs);

        $this->assertCount(1, $svc->descriptionParagraphs('وصف من سطر واحد', 'ar'));
        $this->assertSame([], $svc->descriptionParagraphs("  \n \n ", 'ar'));
    }

    #[Test]
    public function print_text_normalization_is_linguistic_only_and_lossless(): void
    {
        $svc = app(PdfDocumentService::class);

        $clean = fn (string $in): string => $svc->descriptionParagraphs($in, 'ar')[0] ?? '';

        $this->assertSame('العدد 5', $clean("العدد\u{200F} 5"));
        $this->assertSame('ملاحظة، ثم ثانية.', $clean('ملاحظة ،ثم  ثانية.'));
        $this->assertSame('5 ملاحظات و12 كاميرا', $clean('5ملاحظات و12كاميرا'));
        $this->assertSame('كلمتان متصلتان', $clean("كلمتان\x{00A0}متصلتان"));
    }

    #[Test]
    public function draft_report_pdf_is_unavailable(): void
    {
        $writer = User::factory()->reportWriter()->create();
        $report = Report::factory()->for($writer, 'author')->create(['status' => 'draft']);

        $this->getJson("/api/reports/{$report->id}/pdf", $this->auth($writer))
            ->assertStatus(422);
    }

    #[Test]
    public function zip_contains_original_files(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();
        Storage::disk('attachments')->put('t/a.txt', 'hello');
        Storage::disk('attachments')->put('t/b.txt', 'world');
        $note->attachments()->create([
            'file_path' => 't/a.txt', 'original_name' => 'a.txt',
            'mime_type' => 'text/plain', 'file_size' => 5,
        ]);
        $note->attachments()->create([
            'file_path' => 't/b.txt', 'original_name' => 'b.txt',
            'mime_type' => 'text/plain', 'file_size' => 5,
        ]);

        $this->get("/api/notes/{$note->id}/attachments.zip", $this->auth($user))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        $path = app(PdfDocumentService::class)->zipOriginals($note->fresh());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
        @unlink($path);
    }

    #[Test]
    public function http_request_never_generates_inline_but_queues_and_returns_202(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['status' => Note::STATUS_DRAFT]);

        PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->delete();
        Storage::disk('local')->deleteDirectory('pdfs/note/'.$note->id);

        config(['pdf.queue' => true]);
        \Illuminate\Support\Facades\Queue::fake();

        $res = $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($user));
        $this->assertSame(202, $res->status());
        $this->assertFalse($res->json('ready'));

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\GeneratePdfDocument::class,
            fn ($job) => $job->modelId === $note->id
        );

        $row = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->first();
        $this->assertSame(PdfDocument::STATUS_GENERATING, $row->status);
        $this->assertFalse($row->fileExists());

        $this->getJson("/api/notes/{$note->id}/pdf-status", $this->auth($user))
            ->assertStatus(200)->assertJson(['ready' => false]);

        $pushed = 0;
        foreach (\Illuminate\Support\Facades\Queue::pushedJobs() as $jobs) {
            $pushed += count($jobs);
        }
        $this->assertSame(1, $pushed, 'status polling must not dispatch a second job');

        (new \App\Jobs\GeneratePdfDocument(Note::class, $note->id, 'ar'))
            ->handle(app(PdfDocumentService::class));

        $done = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->first();
        $this->assertSame(PdfDocument::STATUS_READY, $done->status);
        $this->assertTrue($done->fileExists());

        $ok = $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($user));
        $this->assertSame(200, $ok->status());
        $this->assertStringStartsWith('%PDF', (string) $ok->getContent());
    }

    #[Test]
    public function stale_generating_row_is_taken_over(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create();

        $existing = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->first();
        if ($existing) {
            $existing->fill([
                'status' => PdfDocument::STATUS_GENERATING,
                'generating_since' => now()->subMinutes(30),
                'content_hash' => 'stale',
            ])->save();
        } else {
            PdfDocument::create([
                'documentable_type' => Note::class,
                'documentable_id' => $note->id,
                'locale' => 'ar',
                'status' => PdfDocument::STATUS_GENERATING,
                'generating_since' => now()->subMinutes(30),
                'content_hash' => 'stale',
                'attempts' => 1,
            ]);
        }

        $fresh = app(PdfDocumentService::class)->resolve($note->fresh(), 'ar');
        $this->assertSame(PdfDocument::STATUS_READY, $fresh->status);
        $this->assertNotSame('stale', $fresh->content_hash);
        $this->assertTrue($fresh->fileExists());
    }
}
