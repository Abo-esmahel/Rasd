<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GeneralSubmission extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'floor_number',
        'camera_number',
        'observed_at',
        'observed_end_at',
        'description',
        'status',
        'rejection_reason',
        'processed_by',
        'sent_at',
        'processed_at',
    ];

    protected $casts = [
        'observed_at' => 'datetime',
        'observed_end_at' => 'datetime',
        'sent_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function reportWriters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'general_submission_report_writer')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GeneralSubmissionAttachment::class, 'general_submission_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
