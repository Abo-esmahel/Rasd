<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';

    const MODE_AI = 'ai';
    const MODE_MANUAL = 'manual';
    const MODE_HYBRID = 'hybrid';

    /** مهلة التعديل بعد النشر بالساعات — بعدها يُقفل التقرير نهائياً. */
    const EDIT_WINDOW_HOURS = 12;

    protected $fillable = [
        'author_id',
        'title',
        'content',
        'summary',
        'recommendations',
        'ai_draft_content',
        'ai_summary',
        'ai_recommendations',
        'ai_sheet_image_path',
        'ai_sheet_generated_at',
        'ai_sheet_data_hash',
        'generation_mode',
        'status',
        'visible_to_monitors',
        'report_date',
        'published_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'published_at' => 'datetime',
        'ai_sheet_generated_at' => 'datetime',
        'visible_to_monitors' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'report_note')
            ->withPivot('order_index')
            ->withTimestamps()
            ->orderByPivot('order_index');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class)->orderByDesc('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** نهاية مهلة التعديل (null للمسودات). */
    public function editDeadline(): ?Carbon
    {
        if (!$this->isPublished()) {
            return null;
        }
        $base = $this->published_at ?? $this->updated_at;
        if (!$base) {
            return null;
        }

        return Carbon::parse($base)->addHours(self::EDIT_WINDOW_HOURS);
    }

    /** true عندما تجاوز التقرير المنشور مهلة التعديل — يُقفل نهائياً. */
    public function isLocked(): bool
    {
        if (!$this->isPublished()) {
            return false;
        }
        $deadline = $this->editDeadline();

        return $deadline === null || now()->greaterThanOrEqualTo($deadline);
    }
}
