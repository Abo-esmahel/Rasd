<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\Pdf\AttachmentSignedUrl;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * روابط المرفقات داخل الـPDF — يجب أن تفتح الملف فعلاً بلا جلسة.
 *
 * العطل الذي عولج: الرابط كان يشير إلى مسار الويب، فقارئ PDF على الجوال
 * يفتحه بلا كوكيز فينتقل إلى صفحة الدخول. الرابط كان يبدو قابلاً للضغط
 * لكنه لم يُري الملف أبداً.
 */
class PdfAttachmentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        Storage::fake('local');
    }

    private function noteWithAttachment(): array
    {
        $user = User::factory()->monitor()->create();
        $note = Note::factory()->for($user, 'owner')->create(['description' => 'وصف']);

        Storage::disk('attachments')->put('notes/x/one.jpg', 'fake-jpeg-bytes');
        $att = $note->attachments()->create([
            'file_path' => 'notes/x/one.jpg',
            'original_name' => 'one.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 14,
        ]);

        return [$user, $note, $att];
    }

    #[Test]
    public function signed_url_points_at_the_public_base_not_localhost(): void
    {
        config(['pdf.public_base_url' => 'http://192.168.10.138:8000']);

        [, , $att] = $this->noteWithAttachment();
        $url = AttachmentSignedUrl::make($att, false);

        $this->assertStringStartsWith('http://192.168.10.138:8000/api/attachments/', $url);
        $this->assertStringContainsString('signed-view', $url);
        $this->assertStringNotContainsString('localhost', $url);
        $this->assertStringNotContainsString('192.168', substr($url, strrpos($url, '?')), 'signature must not carry the host');
    }

    #[Test]
    public function signature_verifies_for_its_own_attachment_only(): void
    {
        [, , $att] = $this->noteWithAttachment();
        $url = AttachmentSignedUrl::make($att, false);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $id = (int) $att->getKey();
        $this->assertTrue(AttachmentSignedUrl::verify($id, $q['e'], $q['s'], false));

        $this->assertFalse(AttachmentSignedUrl::verify($id + 1, $q['e'], $q['s'], false));
        $this->assertFalse(AttachmentSignedUrl::verify($id, $q['e'], $q['s'], true));
        $this->assertFalse(AttachmentSignedUrl::verify($id, $q['e'], 'deadbeef', false));
    }

    #[Test]
    public function expired_signature_is_refused(): void
    {
        [, , $att] = $this->noteWithAttachment();
        $id = (int) $att->getKey();

        $past = time() - 10;
        $url = AttachmentSignedUrl::make($att, false);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $expired = time() - 5;
        $this->assertFalse(AttachmentSignedUrl::verify($id, (string) $expired, $q['s'], false));
        $this->assertTrue(AttachmentSignedUrl::verify($id, $q['e'], $q['s'], false));
    }

    #[Test]
    public function signed_route_serves_the_file_without_any_session(): void
    {
        [, , $att] = $this->noteWithAttachment();
        $url = AttachmentSignedUrl::make($att, false);
        $path = (string) parse_url($url, PHP_URL_PATH);

        $res = $this->get($path.'?'.parse_url($url, PHP_URL_QUERY));

        $res->assertStatus(200);
        $this->assertStringContainsString('image/jpeg', (string) $res->headers->get('content-type'));
    }

    #[Test]
    public function signed_route_refuses_a_missing_or_tampered_signature(): void
    {
        [, , $att] = $this->noteWithAttachment();
        $url = AttachmentSignedUrl::make($att, false);
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $this->get($path)->assertStatus(403);
        $this->get($path.'?e='.$q['e'].'&s=0000')->assertStatus(403);
        $this->get($path.'?e=abc&s='.$q['s'])->assertStatus(403);

        $other = new Attachment();
        $other->id = (int) $att->getKey() + 7;
        parse_str(
            (string) parse_url((string) AttachmentSignedUrl::make($other, false), PHP_URL_QUERY),
            $o,
        );
        $this->get($path.'?e='.$o['e'].'&s='.$o['s'])->assertStatus(403);
    }

    #[Test]
    public function pdf_html_uses_the_signed_url_for_each_attachment(): void
    {
        [, $note] = $this->noteWithAttachment();
        $html = app(PdfDocumentService::class)->buildHtml($note->fresh(), 'ar');

        $this->assertStringContainsString('signed-view', $html);
        $this->assertStringNotContainsString('href="/attachments/', $html);

        preg_match_all('/href="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
            $this->assertStringContainsString('signed-view', $href);
            $this->assertStringContainsString('e=', $href);
            $this->assertMatchesRegularExpression('/[?&]s=[0-9a-f]{32}/', $href);
        }
    }
}