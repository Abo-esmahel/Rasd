<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Forensic405TmpTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(string $name = 'f405.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'f405_');
        file_put_contents($tmp, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP'));
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
