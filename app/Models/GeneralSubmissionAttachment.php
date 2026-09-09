<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralSubmissionAttachment extends Model
{


    protected $fillable = [
        'general_submission_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GeneralSubmission::class, 'general_submission_id');
    }

    public function generalSubmission(): BelongsTo
    {
        return $this->belongsTo(GeneralSubmission::class, 'general_submission_id');
    }

    /**
     * Local disk only (no Cloudinary legacy for submissions — feature is new).
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

    public function viewUrl(): string
    {
        return route('submission-attachments.view', $this);
    }
}
