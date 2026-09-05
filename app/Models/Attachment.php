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
}
