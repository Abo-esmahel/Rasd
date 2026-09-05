<?php

namespace Tests\Feature\Api;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    private function authenticate(): array
    {
        $monitor = User::factory()->monitor()->create();
        $token = $this->jwtService->generateToken($monitor);
        return [$monitor, $token];
    }

    private function createFakeFile(string $name, string $mimeType): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');
        return new UploadedFile($tmpFile, $name, $mimeType, null, true);
    }

    private function createMinimalJpeg(): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        $jpegData = str_repeat("\x00", 100);
        $jpegFooter = "\xFF\xD9";
        file_put_contents($tmpFile, $jpegHeader . $jpegData . $jpegFooter);
        return new UploadedFile($tmpFile, 'photo.jpg', 'image/jpeg', null, true);
    }

    public function test_upload_rejects_dangerous_php_file(): void
    {
        [$monitor, $token] = $this->authenticate();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $file = $this->createFakeFile('malware.php', 'application/x-php');

        $response = $this->postJson("/api/notes/{$note->id}/attachments", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_html_file(): void
    {
        [$monitor, $token] = $this->authenticate();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $file = $this->createFakeFile('page.html', 'text/html');

        $response = $this->postJson("/api/notes/{$note->id}/attachments", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_exe_file(): void
    {
        [$monitor, $token] = $this->authenticate();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $file = $this->createFakeFile('program.exe', 'application/octet-stream');

        $response = $this->postJson("/api/notes/{$note->id}/attachments", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthorized_attachment_access(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor1->id]);
        $attachment = Attachment::factory()->create(['note_id' => $note->id]);

        $token = $this->jwtService->generateToken($monitor2);
        $response = $this->getJson("/api/attachments/{$attachment->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_unauthorized_attachment_deletion(): void
    {
        $monitor1 = User::factory()->monitor()->create();
        $monitor2 = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor1->id]);
        $attachment = Attachment::factory()->create(['note_id' => $note->id]);

        $token = $this->jwtService->generateToken($monitor2);
        $response = $this->deleteJson("/api/notes/{$note->id}/attachments/{$attachment->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_modify_attachments_on_accepted_note(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->accepted()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        $file = $this->createMinimalJpeg();

        $response = $this->postJson("/api/notes/{$note->id}/attachments", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertForbidden();
    }

    public function test_attachment_count_limit(): void
    {
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);
        $token = $this->jwtService->generateToken($monitor);

        for ($i = 0; $i < 5; $i++) {
            Attachment::factory()->create(['note_id' => $note->id]);
        }

        $file = $this->createMinimalJpeg();

        $response = $this->postJson("/api/notes/{$note->id}/attachments", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertBadRequest();
    }

    public function test_download_requires_auth(): void
    {
        $attachment = Attachment::factory()->create();

        $response = $this->getJson("/api/attachments/{$attachment->id}");

        $response->assertUnauthorized();
    }
}
