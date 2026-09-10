<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TmpGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function seedGallery(): User
    {
        Storage::fake('attachments');
        $monitor = User::factory()->monitor()->create();
        $note = Note::factory()->create(['user_id' => $monitor->id, 'camera_number' => 3, 'floor_number' => 2, 'status' => 'pending']);
        $img = Attachment::factory()->create(['note_id' => $note->id, 'mime_type' => 'image/jpeg']);
        $vid = Attachment::factory()->create(['note_id' => $note->id, 'mime_type' => 'video/mp4']);
        Storage::disk('attachments')->put($img->file_path, 'x');
        Storage::disk('attachments')->put($vid->file_path, 'x');
        return $monitor;
    }

    public function test_a_guest_redirect(): void
    {
        $this->seedGallery();
        $this->get('/gallery')->assertRedirect('/login');
    }

    public function test_b_list(): void
    {
        $monitor = $this->seedGallery();
        $this->actingAs($monitor)->get('/gallery')->assertOk()->assertSee('معرض المرفقات', false)->assertSee('gal-grid', false);
    }

    public function test_c_filter(): void
    {
        $monitor = $this->seedGallery();
        $this->actingAs($monitor)->get('/gallery?type=image')->assertOk()->assertSee('gal-grid', false);
    }

    public function test_d_empty(): void
    {
        $monitor = $this->seedGallery();
        $this->actingAs($monitor)->get('/gallery?camera_number=99')->assertOk()->assertSee('لا توجد مرفقات مطابقة', false);
    }

    public function test_e_gating(): void
    {
        $monitor = $this->seedGallery();
        $this->actingAs($monitor)->get('/gallery')->assertOk()->assertDontSee('تنزيل', false);
        $writer = User::factory()->reportWriter()->create();
        $this->actingAs($writer)->get('/gallery')->assertOk()->assertSee('تنزيل', false);
    }
}
