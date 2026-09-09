<?php

namespace App\Services\Media;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryMediaService
{
    private Cloudinary $cloudinary;
    private string $folder;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('filesystems.disks.cloudinary.cloud_name'),
                'api_key' => config('filesystems.disks.cloudinary.api_key'),
                'api_secret' => config('filesystems.disks.cloudinary.api_secret'),
            ],
        ]);
        $this->folder = trim((string) config('filesystems.disks.cloudinary.folder', ''), '/');
    }

    public function getCloudName(): ?string
    {
        return config('filesystems.disks.cloudinary.cloud_name');
    }

    public function isConfigured(): bool
    {
        return !empty(config('filesystems.disks.cloudinary.cloud_name'))
            && !empty(config('filesystems.disks.cloudinary.api_key'))
            && !empty(config('filesystems.disks.cloudinary.api_secret'));
    }

    /**
     * Upload to Cloudinary. Single source of truth for all media (avatar & attachments).
     * Returns normalized CloudinaryMedia with real public_id/resource_type/secure_url.
     */
    public function upload(UploadedFile|string $file, string $folderPrefix = ''): CloudinaryMedia
    {
        $path = is_string($file) ? $file : $file->getPathname();
        $originalName = is_string($file) ? basename($file) : $file->getClientOriginalName();

        // Build public_id: {CLOUDINARY_FOLDER}/{folderPrefix}/{uuid}  (without extension, Cloudinary adds format)
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $subPath = trim($folderPrefix, '/') ? trim($folderPrefix, '/') . '/' . $uuid : $uuid;
        $publicId = $this->folder ? $this->folder . '/' . $subPath : $subPath;

        try {
            $response = $this->cloudinary->uploadApi()->upload($path, [
                'public_id' => $publicId,
                'use_filename' => false,
                'unique_filename' => false,
                'overwrite' => false,
                'invalidate' => true,
                'resource_type' => 'auto',
            ]);
        } catch (\Throwable $e) {
            Log::error('CloudinaryMediaService upload failed', [
                'folderPrefix' => $folderPrefix,
                'public_id' => $publicId,
                'original' => $originalName,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Normalize response — never log api_secret
        Log::info('CloudinaryMediaService upload success', [
            'public_id' => $response['public_id'] ?? $publicId,
            'resource_type' => $response['resource_type'] ?? 'unknown',
            'format' => $response['format'] ?? null,
            'secure_url' => $response['secure_url'] ?? null,
            'asset_id' => $response['asset_id'] ?? null,
            'bytes' => $response['bytes'] ?? null,
        ]);

        return new CloudinaryMedia(
            publicId: $response['public_id'] ?? $publicId,
            resourceType: $response['resource_type'] ?? 'image',
            format: $response['format'] ?? null,
            secureUrl: $response['secure_url'] ?? '',
            assetId: $response['asset_id'] ?? null,
            bytes: (int) ($response['bytes'] ?? 0),
            originalFilename: $originalName,
        );
    }

    /**
     * Delete from Cloudinary using stored public_id + resource_type (never 'auto').
     */
    public function delete(string $publicId, string $resourceType): bool
    {
        $resourceType = $this->normalizeResourceType($resourceType);
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                'invalidate' => true,
                'resource_type' => $resourceType,
            ]);
            $ok = ($result['result'] ?? '') === 'ok' || ($result['result'] ?? '') === 'not found';
            Log::info('CloudinaryMediaService delete', ['public_id' => $publicId, 'resource_type' => $resourceType, 'result' => $result['result'] ?? 'unknown']);
            return $ok;
        } catch (\Throwable $e) {
            Log::warning('CloudinaryMediaService delete failed', ['public_id' => $publicId, 'resource_type' => $resourceType, 'message' => $e->getMessage()]);
            return false;
        }
    }

    private function normalizeResourceType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'image', 'video', 'raw' => $type,
            default => 'image',
        };
    }

    public function getCloudinary(): Cloudinary
    {
        return $this->cloudinary;
    }
}
