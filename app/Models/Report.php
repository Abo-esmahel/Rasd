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

    /**
     * القفل محسوب من published_at + نافذة التعديل، لا عمودٌ في القاعدة.
     * نُسلسله ليتطابق عميل الجوال مع ReportPolicy بدل أن يظنّ نفسه مخوّلاً
     * ثم يُقابَل بخطأ 403 عند السحب أو الحذف.
     */
    protected $appends = [
        'is_locked',
        'edit_deadline',
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

    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class)->whereNull('parent_id')->with(['user', 'reactions', 'replies']);
    }

    public function reactions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

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

    /** يُقرأ من عميل الجوال لتحليل الصلاحيات دون تكرار منطق النافذة. */
    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
    }

    /** موعد نهاية نافذة التعديل — لعرضه للمستخدم قبل القفل. */
    public function getEditDeadlineAttribute(): ?string
    {
        return $this->editDeadline()?->toIso8601String();
    }

    public function isLocked(): bool
    {
        if (!$this->isPublished()) {
            return false;
        }
        $deadline = $this->editDeadline();

        return $deadline === null || now()->greaterThanOrEqualTo($deadline);
    }
}
