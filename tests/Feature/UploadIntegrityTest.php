<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadIntegrityTest extends TestCase
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

    private function jpeg(string $name = 'test-real.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00".str_repeat("\x00", 100)."\xFF\xD9");
        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    private function notePayload(array $extra = []): array
    {
        return array_merge([
            'floor_number' => 1,
            'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف كافٍ لاجتياز التحقق المطلوب هنا',
            'action' => 'save',
        ], $extra);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_no_attachment_intended_is_full_success(): void
    {
        $user = $this->monitor();

        $response = $this->actingAs($user)->postJson('/notes', $this->notePayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 0)
            ->assertJsonPath('attachments_saved', 0)
            ->assertJsonPath('attachment_errors', []);
        $this->assertDatabaseHas('notes', ['id' => $response->json('note_id'), 'user_id' => $user->id]);
    }

    public function test_single_image_saved_with_matching_counts(): void
    {
        $user = $this->monitor();

        $response = $this->actingAs($user)->post('/notes',
            $this->notePayload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->jsonHeaders());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 1)
            ->assertJsonPath('attachments_saved', 1)
            ->assertJsonPath('attachment_errors', []);
        $noteId = $response->json('note_id');
        $this->assertDatabaseHas('attachments', ['note_id' => $noteId, 'original_name' => 'test-real.jpg']);

        $attachment = Attachment::where('note_id', $noteId)->first();
        $this->assertNotNull($attachment);
        $this->assertNull($attachment->secure_url);
        Storage::disk('attachments')->assertExists($attachment->file_path);

        
        $this->actingAs($user)->get("/notes/{$noteId}")->assertOk()->assertSee('test-real.jpg');
        $this->actingAs($user)->get("/notes/{$noteId}")->assertOk()->assertSee('test-real.jpg');
    }

    public function test_multiple_files_counts_match(): void
    {
        $user = $this->monitor();

        $response = $this->actingAs($user)->post('/notes',
            $this->notePayload([
                'files' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg')],
                'client_files_count' => 3,
            ]),
            $this->jsonHeaders());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('files_received', 3)
            ->assertJsonPath('attachments_saved', 3);
        $this->assertEquals(3, Attachment::where('note_id', $response->json('note_id'))->count());
        foreach (Attachment::where('note_id', $response->json('note_id'))->get() as $a) {
            Storage::disk('attachments')->assertExists($a->file_path);
        }
    }

    
    public function test_declared_but_not_received_is_not_full_success_and_note_kept(): void
    {
        $user = $this->monitor();

        $response = $this->actingAs($user)->postJson('/notes',
            $this->notePayload(['client_files_count' => 1]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('files_received', 0)
            ->assertJsonPath('attachments_saved', 0);
        $this->assertNotEmpty($response->json('attachment_errors'));
        $this->assertStringContainsString('لم يصل', implode(' ', array_map(fn ($e) => is_array($e) ? json_encode($e, JSON_UNESCAPED_UNICODE) : $e, $response->json('attachment_errors'))));

        
        $this->assertNull($response->json('note_id'));
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
    }

    public function test_update_declared_but_not_received_is_not_full_success(): void
    {
        $user = $this->monitor();
        $note = Note::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/notes/{$note->id}", [
            'floor_number' => 1,
            'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف محدث كافٍ لاجتياز التحقق المطلوب',
            'client_files_count' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('files_received', 0)
            ->assertJsonPath('attachments_saved', 0);
        
        $this->assertDatabaseHas('notes', ['id' => $note->id]);
        $this->assertEquals(0, Attachment::where('note_id', $note->id)->count());
    }

    public function test_file_with_upload_error_is_not_silent_success(): void
    {
        $user = $this->monitor();
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, 'x');
        
        $bad = new UploadedFile($tmp, 'big.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $response = $this->actingAs($user)->post('/notes',
            $this->notePayload(['files' => [$bad], 'client_files_count' => 1]),
            $this->jsonHeaders());

        
        
        $response->assertStatus(422);
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
        $this->assertEquals(0, \App\Models\Attachment::count());
        if ($response->json('success') !== null) {
            $this->assertFalse($response->json('success'));
            $this->assertNotEmpty($response->json('attachment_errors'));
            $flat = implode(' ', array_map(fn ($e) => is_array($e) ? json_encode($e, JSON_UNESCAPED_UNICODE) : $e, (array) $response->json('attachment_errors')));
            $this->assertStringContainsString('big.jpg', $flat);
        } else {
            
            $this->assertNotEmpty($response->json('errors') ?? $response->json('message'));
        }
    }

    public function test_full_reload_keeps_attachment(): void
    {
        $user = $this->monitor();

        $created = $this->actingAs($user)->post('/notes',
            $this->notePayload(['files' => [$this->jpeg()], 'client_files_count' => 1]),
            $this->jsonHeaders());
        $created->assertCreated();
        $noteId = $created->json('note_id');

        
        $fresh = Note::with('attachments')->find($noteId);
        $this->assertEquals(1, $fresh->attachments->count());
        Storage::disk('attachments')->assertExists($fresh->attachments->first()->file_path);

        $this->actingAs($user)->get("/notes/{$noteId}")->assertOk()->assertSee('test-real.jpg');
        $this->assertEquals(1, Note::with('attachments')->find($noteId)->attachments->count());
        Storage::disk('attachments')->assertExists(Note::with('attachments')->find($noteId)->attachments->first()->file_path);
    }

    
    public function test_received_but_not_saved_is_not_full_success(): void
    {
        $user = $this->monitor();
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, 'plain text content that fails extension allow-list');
        $rejected = new UploadedFile($tmp, 'note.txt', 'text/plain', null, true);

        $response = $this->actingAs($user)->post('/notes',
            $this->notePayload(['files' => [$rejected], 'client_files_count' => 1]),
            $this->jsonHeaders());

        
        $response->assertStatus(422);
        if ($response->json('success') !== null) {
            $this->assertFalse($response->json('success'));
        }
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
        $this->assertEquals(0, Attachment::count());
    }

    
    public function test_partial_transport_loss_is_not_full_success(): void
    {
        $user = $this->monitor();

        $response = $this->actingAs($user)->post('/notes',
            $this->notePayload(['files' => [$this->jpeg()], 'client_files_count' => 2]),
            $this->jsonHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('files_received', 1)
            ->assertJsonPath('attachments_saved', 0);
        $this->assertNotEmpty($response->json('attachment_errors'));
        $this->assertEquals(0, Note::where('user_id', $user->id)->count());
    }

    
    public function test_upload_forms_use_same_origin_relative_actions(): void
    {
        $user = $this->monitor();

        $create = $this->actingAs($user)->get('/notes/create');
        $create->assertOk();
        $create->assertSee('action="/notes"', false);

        $note = Note::factory()->draft()->create(['user_id' => $user->id]);
        $edit = $this->actingAs($user)->get("/notes/{$note->id}/edit");
        $edit->assertOk();
        $edit->assertSee('action="/notes/'.$note->id.'"', false);
        
        $this->assertDoesNotMatchRegularExpression(
            '#<form[^>]*id="edit-form"[^>]*>.*<form[^>]*action="[^"]*attachments[^"]*"#s',
            $edit->getContent()
        );
    }
}
