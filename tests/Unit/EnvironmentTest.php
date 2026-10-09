<?php

namespace Tests\Unit;

use App\Services\Pdf\PdfDocumentService;
use App\Support\LocalBinary;
use PHPUnit\Framework\TestCase;

/**
 * دخاني استقلالية البيئة — خالص بلا قاعدة بيانات ولا شبكة:
 * اكتشاف الثنائيات لا يرمي أبداً، وحدود الطباعة متسقة.
 */
class EnvironmentTest extends TestCase
{
    public function test_file_url_unix_path(): void
    {
        $this->assertSame('file:///tmp/a.html', LocalBinary::fileUrl('/tmp/a.html'));
    }

    public function test_file_url_windows_path(): void
    {
        $this->assertSame('file:///C:/x/a.html', LocalBinary::fileUrl('C:\\x\\a.html'));
    }

    public function test_find_chrome_never_throws(): void
    {
        $found = LocalBinary::findChrome();
        $this->assertTrue($found === null || is_string($found));
    }

    public function test_find_ffmpeg_never_throws(): void
    {
        $found = LocalBinary::findFfmpeg();
        $this->assertTrue($found === null || is_string($found));
    }

    public function test_find_on_path_missing_binary_returns_null(): void
    {
        $this->assertNull(LocalBinary::findOnPath('rasd-definitely-missing-bin-xyz'));
    }

    public function test_print_html_cap_is_15mb(): void
    {
        $this->assertSame(15 * 1024 * 1024, PdfDocumentService::MAX_HTML_BYTES);
    }
}
