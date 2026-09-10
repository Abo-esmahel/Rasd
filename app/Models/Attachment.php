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
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    
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

    public function storageType(): string
    {
        return $this->isLocal() ? 'local' : 'missing';
    }

    public function viewUrl(): string
    {
        return route('notes.attachments.view', $this);
    }

    public function getResourceTypeAttribute(): string
    {
        if (str_contains($this->mime_type, 'image/')) {
            return 'image';
        }
        if (str_contains($this->mime_type, 'video/')) {
            return 'video';
        }
        if (str_contains($this->mime_type, 'audio/')) {
            return 'raw';
        }
        
        return 'image';
    }
}
