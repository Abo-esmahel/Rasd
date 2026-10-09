<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\PdfDocument;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مسار endpoint PDF بعد نقل التوليد إلى الطابور:
 * الطلب يرد 202 فوراً، والعامل يولّد، ولا تبقى ملفات يتيمة على القرص.
 */
class PdfQueueFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        Storage::fake('local');
        config(['pdf.queue' => true]);
    }

    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->generateToken($user)];
    }

    #[Test]
    public function queued_flow_ends_with_a_served_pdf_and_no_orphan_files(): void
    {
        $user = User::factory()->monitor()->create();

        \Illuminate\Support\Facades\Queue::fake();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'وصف للاختبار']);

        $pending = $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($user));
        $this->assertSame(202, $pending->status());

        (new \App\Jobs\GeneratePdfDocument(Note::class, $note->id, 'ar'))
            ->handle(app(\App\Services\Pdf\PdfDocumentService::class));

        $ok = $this->getJson("/api/notes/{$note->id}/pdf", $this->auth($user));
        $this->assertSame(200, $ok->status());
        $this->assertStringStartsWith('%PDF', (string) $ok->getContent());

        $files = Storage::disk('local')->files("pdfs/note/{$note->id}");
        $this->assertCount(1, $files, 'exactly one file must remain — no orphans');
    }

    #[Test]
    public function regeneration_prunes_the_previous_file(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'قبل التعديل']);

        app(\App\Services\Pdf\PdfDocumentService::class)->resolve($note->fresh(), 'ar');
        $dir = "pdfs/note/{$note->id}";
        $first = Storage::disk('local')->files($dir);
        $this->assertCount(1, $first);

        $note->forceFill(['description' => 'بعد التعديل'])->save();
        app(\App\Services\Pdf\PdfDocumentService::class)->resolve($note->fresh(), 'ar');

        $files = Storage::disk('local')->files($dir);
        $this->assertCount(1, $files, 'stale PDF must be pruned, not accumulate');
        $this->assertNotSame($first[0], $files[0]);

        $row = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->first();
        $this->assertTrue($row->fileExists(), 'row must point at a file that exists');
        $this->assertSame($files[0], $row->file_path);
    }

    #[Test]
    public function pruning_never_touches_another_locale_pdf(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'قبل التعديل']);

        $svc = app(\App\Services\Pdf\PdfDocumentService::class);
        $svc->resolve($note->fresh(), 'ar');
        $svc->resolve($note->fresh(), 'en');
        $dir = "pdfs/note/{$note->id}";
        $before = Storage::disk('local')->files($dir);
        $this->assertCount(2, $before, 'ar and en must coexist');

        $note->forceFill(['description' => 'بعد التعديل'])->save();
        $svc->resolve($note->fresh(), 'ar');

        $files = Storage::disk('local')->files($dir);
        $this->assertCount(2, $files, 'the other locale PDF must survive regeneration');

        $enRow = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'en')->first();
        $this->assertTrue($enRow->fileExists(), 'en row must still point at its file');
    }

    #[Test]
    public function row_survives_a_missing_file_and_regenerates(): void
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'محتوى']);

        $svc = app(\App\Services\Pdf\PdfDocumentService::class);
        $svc->resolve($note->fresh(), 'ar');

        $row = PdfDocument::where('documentable_type', Note::class)
            ->where('documentable_id', $note->id)->where('locale', 'ar')->first();
        Storage::disk('local')->delete($row->file_path);
        $this->assertFalse($row->fresh()->fileExists());

        $again = $svc->ensureQueued($note->fresh(), 'ar');
        $this->assertTrue($again->fileExists(), 'a ready row must never point at a missing file');
        $this->assertStringStartsWith(
            '%PDF',
            $svc->pdfBytes($again),
        );
    }
}