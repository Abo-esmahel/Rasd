<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'file_path',
        'cloudinary_resource_type',
        'cloudinary_format',
        'secure_url',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * Local-disk helpers. Source of truth for NEW files: Disk + file_path.
     * Cloudinary fields (secure_url / public_id) are LEGACY READ-ONLY fallback.
     */
    public function isLocal(): bool
    {
        if (!$this->file_path) {
            return false;
        }
        try {
            return \Illuminate\Support\Facades\Storage::disk('attachments')->exists($this->file_path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isLegacyCloudinary(): bool
    {
        return !$this->isLocal() && !empty($this->secure_url);
    }

    public function storageType(): string
    {
        if ($this->isLocal()) {
            return 'local';
        }
        if (!empty($this->secure_url)) {
            return 'cloudinary_legacy';
        }

        return 'missing';
    }

    public function viewUrl(): string
    {
        return route('notes.attachments.view', $this);
    }

    public function getCloudinaryPath(): string
    {
        if (!$this->file_path) {
            return '';
        }
        $folder = config('filesystems.disks.cloudinary.folder', '');
        if ($folder && !str_starts_with($this->file_path, $folder . '/')) {
            return $folder . '/' . $this->file_path;
        }
        return $this->file_path;
    }

    public function getUrlAttribute(): ?string
    {
        // Local files MUST be served via /attachments/{id}/view, never via Cloudinary.
        if ($this->isLocal()) {
            return null;
        }
        if ($this->secure_url) {
            return $this->secure_url;
        }
        $cloudinaryPath = $this->getCloudinaryPath();
        if (!$cloudinaryPath) {
            return null;
        }
        $resourceType = $this->cloudinary_resource_type ?: $this->resource_type;
        try {
            $cloudinary = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => config('filesystems.disks.cloudinary.cloud_name', ''),
                    'api_key' => config('filesystems.disks.cloudinary.api_key', ''),
                    'api_secret' => config('filesystems.disks.cloudinary.api_secret', ''),
                ],
            ]);
            return match($resourceType) {
                'video' => $cloudinary->video($cloudinaryPath)->toUrl(),
                'raw' => $cloudinary->raw($cloudinaryPath)->toUrl(),
                default => $cloudinary->image($cloudinaryPath)->toUrl(),
            };
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cloudinary URL generation failed for attachment: '.$e->getMessage(), ['file_path' => $this->file_path]);
        }
        try {
            return 'https://res.cloudinary.com/' . config('filesystems.disks.cloudinary.cloud_name') . '/' . $resourceType . '/upload/' . $cloudinaryPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getResourceTypeAttribute(): string
    {
        if ($this->cloudinary_resource_type) {
            return $this->cloudinary_resource_type;
        }
        return match(true) {
            str_contains($this->mime_type, 'image/') => 'image',
            str_contains($this->mime_type, 'video/') => 'video',
            str_contains($this->mime_type, 'audio/') => 'raw',
            default => 'image',
        };
    }
}
