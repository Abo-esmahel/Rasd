<?php

namespace Tests\Feature;

use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\AttachmentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalAttachmentStorageTest extends TestCase
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

    private function payload(array $extra = []): array
    {
        return array_merge([
            'floor_number' => 1,
            'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف كافٍ لاجتياز التحقق المطلوب هنا',
            'action' => 'save',
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

    public function test_1_create_note_without_attachment(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->postJson('/notes', $this->payload());
        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 0)
            ->assertJsonPath('attachments_saved', 0);
        $this->assertDatabaseHas('notes', ['id' => $res->json('note_id')]);
    }

    public function test_2_create_note_with_single_image(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg('test.jpg')], 'client_files_count' => 1]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 1)
            ->assertJsonPath('attachments_saved', 1);

        $noteId = $res->json('note_id');
        $attachment = Attachment::where('note_id', $noteId)->first();
        $this->assertNotNull($attachment);
        $this->assertNull($attachment->secure_url);
        $this->assertStringStartsWith("notes/{$noteId}/", $attachment->file_path);
        Storage::disk('attachments')->assertExists($attachment->file_path);

        
        $view = $this->actingAs($user)->get("/attachments/{$attachment->id}/view");
        $view->assertOk();
        $this->assertStringStartsWith('image/', $view->headers->get('Content-Type'));
        $stored = Storage::disk('attachments')->get($attachment->file_path);
        $this->assertNotEmpty($stored);
        $servedPath = $view->getFile()->getPathname();
        $this->assertFileExists($servedPath);
        $this->assertEquals($stored, file_get_contents($servedPath));
    }

    public function test_3_create_note_with_three_images(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg')], 'client_files_count' => 3]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 3)
            ->assertJsonPath('attachments_saved', 3);

        $noteId = $res->json('note_id');
        $this->assertEquals(3, Attachment::where('note_id', $noteId)->count());
        $paths = Attachment::where('note_id', $noteId)->pluck('file_path');
        
        $this->assertEquals(3, $paths->unique()->count());
        foreach ($paths as $p) {
            Storage::disk('attachments')->assertExists($p);
        }
    }

    public function test_4_image_video_audio(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg('p.jpg'), $this->mp4('v.mp4'), $this->wav('a.wav')], 'client_files_count' => 3]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 3)
            ->assertJsonPath('attachments_saved', 3);

        $noteId = $res->json('note_id');
        $byMime = Attachment::where('note_id', $noteId)->get()->groupBy(fn ($a) => explode('/', $a->mime_type)[0]);
        $this->assertTrue(isset($byMime['image']) && isset($byMime['video']) && isset($byMime['audio']));

        foreach (Attachment::where('note_id', $noteId)->get() as $a) {
            Storage::disk('attachments')->assertExists($a->file_path);
            $view = $this->actingAs($user)->get("/attachments/{$a->id}/view");
            $view->assertOk();
            $this->assertFileExists($view->getFile()->getPathname());
            $this->assertNotEmpty(file_get_contents($view->getFile()->getPathname()));
        }
    }

    public function test_5_view_returns_correct_content_type_and_bytes(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());
        $attachment = Attachment::where('note_id', $res->json('note_id'))->first();

        $view = $this->actingAs($user)->get("/attachments/{$attachment->id}/view");
        $view->assertOk();
        $this->assertEquals('image/jpeg', explode(';', $view->headers->get('Content-Type'))[0]);
        $this->assertEquals(
            Storage::disk('attachments')->get($attachment->file_path),
            file_get_contents($view->getFile()->getPathname())
        );
    }

    public function test_6_full_reload_persistence(): void
    {
        $user = $this->monitor();
        $created = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());
        $noteId = $created->json('note_id');

        $fresh = Note::with('attachments')->find($noteId);
        $this->assertNotNull($fresh);
        $this->assertEquals(1, $fresh->attachments->count());
        Storage::disk('attachments')->assertExists($fresh->attachments->first()->file_path);

        
        $this->actingAs($user)->get("/notes/{$noteId}")->assertOk()->assertSee($fresh->attachments->first()->original_name);
        $reloaded = Note::with('attachments')->find($noteId);
        $this->assertEquals(1, $reloaded->attachments->count());
        Storage::disk('attachments')->assertExists($reloaded->attachments->first()->file_path);
        $this->actingAs($user)->get("/attachments/{$reloaded->attachments->first()->id}/view")->assertOk();
    }

    public function test_7_storage_failure_no_false_success_no_orphan_cleanup(): void
    {
        $user = $this->monitor();
        $tmp = tempnam(sys_get_temp_dir(), 't_');
        file_put_contents($tmp, 'plain text must fail allow-list');
        $bad = new UploadedFile($tmp, 'note.txt', 'text/plain', null, true);

        
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg('ok.jpg'), $bad], 'client_files_count' => 2]),
            $this->headers());

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
        $this->assertEquals(0, Attachment::count());
        $this->assertEquals([], Storage::disk('attachments')->allFiles('notes'));
    }

    public function test_single_storage_exception_no_orphan(): void
    {
        $mock = $this->mock(AttachmentStorageService::class);
        $mock->shouldReceive('store')->once()->andThrow(new AttachmentUploadException(
            'Simulated disk failure', stage: 'storage', originalName: 'photo.jpg',
            filesReceived: 1, attachmentsSaved: 0, attachmentErrors: ['Simulated disk failure'],
        ));
        
        $mock->shouldReceive('exists')->andReturn(false);
        $mock->shouldReceive('delete')->andReturn(true);
        $mock->shouldReceive('isLocal')->andReturn(false);

        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
        $this->assertEquals(0, Attachment::count());
    }

    public function test_delete_attachment_removes_db_and_file(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());
        $noteId = $res->json('note_id');
        $attachment = Attachment::where('note_id', $noteId)->first();
        $path = $attachment->file_path;
        Storage::disk('attachments')->assertExists($path);

        $del = $this->actingAs($user)->deleteJson("/notes/{$noteId}/attachments/{$attachment->id}");
        $del->assertStatus(204);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('attachments')->assertMissing($path);
    }

    public function test_missing_local_file_returns_404(): void
    {
        $user = $this->monitor();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);
        $legacy = Attachment::factory()->create([
            'note_id' => $note->id,
            'file_path' => 'legacy/notes/'.$note->id.'/old-file',
            'original_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->assertFalse($legacy->fresh()->isLocal());

        $res = $this->actingAs($user)->get("/attachments/{$legacy->id}/view");
        $res->assertNotFound();
    }

    public function test_new_uploads_are_stored_locally(): void
    {
        $user = $this->monitor();
        $res = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());

        $res->assertCreated()->assertJsonPath('success', true);
        $attachment = Attachment::where('note_id', $res->json('note_id'))->first();
        $this->assertNotNull($attachment);
        $this->assertTrue(Storage::disk('attachments')->exists($attachment->file_path));
    }

    public function test_update_with_new_attachments_is_atomic(): void
    {
        $user = $this->monitor();
        $created = $this->actingAs($user)->postJson('/notes', $this->payload());
        $noteId = $created->json('note_id');

        $upd = $this->actingAs($user)->put("/notes/{$noteId}", array_merge(
            $this->payload(['description' => 'وصف محدث كافٍ لاجتياز التحقق المطلوب مع مرفق']),
            ['files' => [$this->jpeg('new.jpg')], 'client_files_count' => 1]
        ), $this->headers());

        $upd->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(1, Attachment::where('note_id', $noteId)->count());
        Storage::disk('attachments')->assertExists(Attachment::where('note_id', $noteId)->first()->file_path);
    }

    public function test_download_for_report_writer_streams_local_file(): void
    {
        $writer = $this->writer();
        $created = $this->actingAs($writer)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->headers());
        $attachment = Attachment::where('note_id', $created->json('note_id'))->first();

        $dl = $this->actingAs($writer)->get("/attachments/{$attachment->id}/download");
        $dl->assertOk();
        $this->assertEquals(
            Storage::disk('attachments')->get($attachment->file_path),
            file_get_contents($dl->getFile()->getPathname())
        );
    }

    public function test_missing_local_and_no_secure_url_returns_404(): void
    {
        $user = $this->monitor();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);
        $ghost = Attachment::factory()->create([
            'note_id' => $note->id,
            'file_path' => 'notes/'.$note->id.'/ghost-uuid.jpg',
        ]);
        $this->assertFalse(Storage::disk('attachments')->exists($ghost->file_path));

        $this->actingAs($user)->get("/attachments/{$ghost->id}/view")->assertNotFound();
    }
}
