<?php

namespace App\Console\Commands;

use App\Services\Media\CloudinaryMediaService;
use Illuminate\Console\Command;

class CloudinaryDiagnose extends Command
{
    protected $signature = 'cloudinary:diagnose';
    protected $description = 'Diagnose Cloudinary SDK, config and real upload/delivery';

    public function handle(CloudinaryMediaService $media): int
    {
        $this->line('=================================');
        $this->line('CLOUDINARY DIAGNOSE');
        $this->line('=================================');
        $this->line('');

        // SDK
        $sdkOk = class_exists(\Cloudinary\Cloudinary::class);
        $this->line('Cloudinary SDK initialized: ' . ($sdkOk ? 'YES' : 'NO'));
        $this->line('SDK version: ' . (\Cloudinary\Cloudinary::VERSION ?? 'unknown'));
        $this->line('');

        // Config (never log secret)
        $cloudName = config('filesystems.disks.cloudinary.cloud_name');
        $apiKey = config('filesystems.disks.cloudinary.api_key');
        $apiSecret = config('filesystems.disks.cloudinary.api_secret');
        $folder = config('filesystems.disks.cloudinary.folder');
        $this->line('Cloud name: ' . ($cloudName ?: 'MISSING'));
        $this->line('API key present: ' . ($apiKey ? 'YES (' . substr($apiKey, 0, 4) . '****)' : 'NO'));
        $this->line('API secret present: ' . ($apiSecret ? 'YES' : 'NO'));
        $this->line('Folder: ' . ($folder ?: '(none)'));
        $this->line('Configured: ' . ($media->isConfigured() ? 'YES' : 'NO'));
        $this->line('Upload API available: ' . (method_exists($media->getCloudinary()->uploadApi(), 'upload') ? 'YES' : 'NO'));
        $this->line('');

        if (!$media->isConfigured()) {
            $this->error('Cloudinary not configured — aborting upload test');
            return 1;
        }

        // Real upload test — tiny PNG
        $this->line('--- REAL UPLOAD TEST ---');
        $tmp = tempnam(sys_get_temp_dir(), 'diag');
        file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
        $file = new \Illuminate\Http\UploadedFile($tmp, 'diag.png', 'image/png', null, true);
        try {
            $result = $media->upload($file, 'health/diag');
            $this->line('Upload: PASS');
            $this->line('  public_id: ' . $result->publicId);
            $this->line('  resource_type: ' . $result->resourceType);
            $this->line('  format: ' . ($result->format ?: 'null'));
            $this->line('  secure_url: ' . $result->secureUrl);
            $this->line('  asset_id: ' . ($result->assetId ?: 'null'));

            // Delivery test
            $ch = curl_init($result->secureUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            $this->line('Delivery HTTP: ' . $code . ' Content-Type: ' . $ct . ' ' . ($code === 200 ? 'PASS' : 'FAIL'));

            // Delete test
            $deleted = $media->delete($result->publicId, $result->resourceType);
            $this->line('Delete: ' . ($deleted ? 'PASS' : 'FAIL'));
            // Verify gone
            $ch = curl_init($result->secureUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->line('Post-delete HTTP: ' . $code2 . ' (expected 404) ' . ($code2 === 404 ? 'PASS' : 'CHECK'));

            $this->line('');
            $this->line('DIAGNOSE COMPLETE');
            @unlink($tmp);
            return $code === 200 && $deleted ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error('Upload FAILED: ' . $e->getMessage());
            @unlink($tmp);
            return 1;
        }
    }
}
