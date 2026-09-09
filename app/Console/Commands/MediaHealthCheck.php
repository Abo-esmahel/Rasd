<?php

namespace App\Console\Commands;

use App\Models\Note;
use App\Models\User;
use App\Services\Media\CloudinaryMediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class MediaHealthCheck extends Command
{
    protected $signature = 'media:health-check';
    protected $description = 'Health check for Cloudinary, avatar and note attachments';

    public function handle(CloudinaryMediaService $media): int
    {
        $this->line('=================================');
        $this->line('CLOUDINARY HEALTH CHECK');
        $this->line('=================================');
        $this->line('');

        $this->line('SDK: ' . (class_exists(\Cloudinary\Cloudinary::class) ? 'PASS' : 'FAIL'));
        $this->line('Configuration: ' . ($media->isConfigured() ? 'PASS' : 'FAIL'));

        // Isolated Cloudinary test
        $tmp = tempnam(sys_get_temp_dir(), 'hc');
        file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
        $file = new UploadedFile($tmp, 'hc.png', 'image/png', null, true);
        try {
            $r = $media->upload($file, 'health/check');
            $this->line('Upload: PASS (' . $r->publicId . ' ' . $r->resourceType . ')');
            $ch = curl_init($r->secureUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->line('Delivery: ' . ($code === 200 ? 'PASS' : "FAIL ($code)"));
            $del = $media->delete($r->publicId, $r->resourceType);
            $this->line('Delete: ' . ($del ? 'PASS' : 'FAIL'));
        } catch (\Throwable $e) {
            $this->line('Upload: FAIL ' . $e->getMessage());
            @unlink($tmp);
            return 1;
        }
        @unlink($tmp);
        $this->line('');

        // Profile avatar test
        $this->line('PROFILE AVATAR:');
        try {
            $user = User::first();
            if (!$user) throw new \RuntimeException('No user');
            $tmp = tempnam(sys_get_temp_dir(), 'ava');
            file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
            $f = new UploadedFile($tmp, 'avatar.png', 'image/png', null, true);
            $r = $media->upload($f, 'avatars');
            $this->line('  Upload: PASS ' . $r->secureUrl);
            $ch = curl_init($r->secureUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->line('  Delivery: ' . ($code === 200 ? 'PASS' : "FAIL $code"));
            $this->line('  DB: SKIP (isolated test)');
            $media->delete($r->publicId, $r->resourceType);
            @unlink($tmp);
        } catch (\Throwable $e) {
            $this->line('  FAIL ' . $e->getMessage());
        }
        $this->line('');

        // Note image test via MediaService + DB
        $this->line('NOTE IMAGE:');
        try {
            $user = User::where('role', 'monitor')->first() ?? User::first();
            $note = Note::create(['user_id' => $user->id, 'floor_number' => 1, 'camera_number' => 1, 'observed_at' => now(), 'description' => 'health', 'status' => Note::STATUS_DRAFT]);
            $tmp = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
            $f = new UploadedFile($tmp, 'photo.png', 'image/png', null, true);
            $r = $media->upload($f, 'notes/' . $note->id);
            $att = \App\Models\Attachment::create([
                'note_id' => $note->id,
                'file_path' => $r->publicId,
                'cloudinary_resource_type' => $r->resourceType,
                'cloudinary_format' => $r->format,
                'secure_url' => $r->secureUrl,
                'original_name' => $f->getClientOriginalName(),
                'mime_type' => $f->getMimeType(),
                'file_size' => $f->getSize(),
            ]);
            $this->line('  Upload: PASS ' . $r->publicId . ' ' . $r->resourceType);
            $this->line('  DB: PASS id=' . $att->id);
            $ch = curl_init($r->secureUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->line('  Delivery: ' . ($code === 200 ? 'PASS' : "FAIL $code"));
            $note->delete();
            @unlink($tmp);
        } catch (\Throwable $e) {
            $this->line('  FAIL ' . $e->getMessage());
        }
        $this->line('');

        $this->line('NOTE AUDIO: SKIP (requires real file, see PHASE 4)');
        $this->line('NOTE VIDEO: SKIP (requires real file)');
        $this->line('');
        $this->line('HEALTH CHECK DONE');
        return 0;
    }
}
