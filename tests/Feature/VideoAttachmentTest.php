<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Video attachments end-to-end: upload (API + Web), MOV + Arabic names,
 * and Range streaming required by <video> players.
 */
class VideoAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
        Storage::fake('attachments');
    }

    private function makeVideo(string $name, int $sizeMb, string $brand = 'mp42', string $clientMime = 'video/mp4'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vid_');
        $ftyp = pack('N', 24).'ftyp'.$brand.pack('N', 0).'isom';
        file_put_contents($tmp, $ftyp.str_repeat("\x00", max(0, $sizeMb * 1024 * 1024 - strlen($ftyp))));
        return new UploadedFile($tmp, $name, $clientMime, null, true);
    }

    private function authMonitor(): array
    {
        $monitor = User::factory()->monitor()->create();
        return [$monitor, $this->jwtService->generateToken($monitor)];
    }

    /** @test */
    public function test_api_accepts_mp4_video_upload(): void
    {
        [$monitor, $token] = $this->authMonitor();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/notes/{$note->id}/attachments", ['file' => $this->makeVideo('clip.mp4', 3)]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.mime_type', 'video/mp4');
        Storage::disk('attachments')->assertExists($res->json('data.file_path'));
    }

    /** @test */
    public function test_api_accepts_mov_with_arabic_filename(): void
    {
        [$monitor, $token] = $this->authMonitor();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/notes/{$note->id}/attachments", [
                'file' => $this->makeVideo('فيديو الملاحظة 12.MOV', 4, 'qt  ', 'video/quicktime'),
            ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.original_name', 'فيديو الملاحظة 12.MOV');
    }

    /** @test */
    public function test_web_note_create_with_video(): void
    {
        $monitor = User::factory()->monitor()->create();

        $res = $this->actingAs($monitor)->post('/notes', [
            'floor_number' => 1,
            'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d H:i'),
            'description' => 'وصف تجريبي طويل بما يكفي للتحقق من الصحة',
            'files' => [$this->makeVideo('clip.mp4', 3)],
            'client_files_count' => 1,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);
        $res->assertJsonPath('attachments_saved', 1);
    }

    /** @test */
    public function test_video_view_supports_range_requests(): void
    {
        [$monitor, $token] = $this->authMonitor();
        $note = Note::factory()->draft()->create(['user_id' => $monitor->id]);

        $up = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/notes/{$note->id}/attachments", ['file' => $this->makeVideo('clip.mp4', 3)]);
        $up->assertStatus(201);
        $attId = $up->json('data.id');
        $this->assertNotNull($attId);

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->withHeaders($headers)->get("/api/attachments/{$attId}/view")->assertStatus(200);

        // What every <video> player sends — must be 206, not 200
        $range = $this->withHeaders($headers + ['Range' => 'bytes=0-1023'])->get("/api/attachments/{$attId}/view");
        $range->assertStatus(206);
        $range->assertHeader('Content-Range', 'bytes 0-1023/'.(3 * 1024 * 1024));
        $range->assertHeader('Accept-Ranges', 'bytes');
    }
}
