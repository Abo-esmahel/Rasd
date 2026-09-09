<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// TEMP 405 FORENSIC — Case A vs B vs C
class Forensic405TmpTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(string $name = 'f405.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'f405_');
        file_put_contents($tmp, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////2wBDAf//////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AH//Z'));
        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'floor_number' => 1, 'camera_number' => 2,
            'observed_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'وصف كاف لاجتياز التحقق المطلوب هنا',
            'action' => 'save',
        ], $extra);
    }

    public function test_405_forensic_abc(): void
    {
        $this->mock(\App\Services\Media\CloudinaryMediaService::class, function ($m) {
            $m->shouldReceive('upload')->andReturn(new \App\Services\Media\CloudinaryMedia(
                publicId: 't/p', resourceType: 'image', format: 'jpg',
                secureUrl: 'https://x.test/t.jpg', assetId: 'a', bytes: 1, originalFilename: 'f.jpg'));
        });
        $user = User::factory()->monitor()->create();
        $ajax = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $a = $this->actingAs($user)->post('/notes', $this->payload(), $ajax);
        fwrite(STDERR, "\n[CASE-A nofile/ajax] status=".$a->getStatusCode()." allow=".$a->headers->get('Allow')."\n");

        $b = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]), $ajax);
        fwrite(STDERR, '[CASE-B file/ajax] status='.$b->getStatusCode().' allow='.$b->headers->get('Allow').' body='.substr($b->getContent(), 0, 300)."\n");

        $c = $this->actingAs($user)->post('/notes',
            $this->payload(['files' => [$this->jpeg()], 'client_files_count' => 1]));
        fwrite(STDERR, '[CASE-C file/native] status='.$c->getStatusCode().' allow='.$c->headers->get('Allow').' location='.$c->headers->get('Location')."\n");

        $this->assertTrue(true);
    }
}
