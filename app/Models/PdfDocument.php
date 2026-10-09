<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PdfDocument extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    /** بعد هذه المدة يُعتبر التوليد العالق ميتاً ويُستولى عليه. */
    public const GENERATING_TTL_MINUTES = 5;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'locale',
        'content_hash',
        'status',
        'file_path',
        'file_size',
        'attempts',
        'error',
        'generating_since',
        'generated_at',
    ];

    protected $casts = [
        'generating_since' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isGeneratingFresh(): bool
    {
        return $this->status === self::STATUS_GENERATING
            && $this->generating_since
            && $this->generating_since->gt(now()->subMinutes(self::GENERATING_TTL_MINUTES));
    }

    /**
     *Generating تجاوز مدة سماح قصيرة → لا عامل يعمل.
     *
     * الفارق عن isGeneratingFresh(): هذا ليس "العامل مشغول الآن" (5 دقائق
     *+nق会说 إنه بخير)، بل "مرّت ثوانٍ قليلة ولم يتغيّر شيء" — وهو ما يحدث
     * تحديداً حين لا يكون هناك queue:work يلتقط المهمة أصلاً. عندها يُنتقل
     * إلى التوليد داخل الطلب بدل الانتظار إلى الأبد.
     */
    public function isGeneratingStuck(int $graceSeconds): bool
    {
        return $this->status === self::STATUS_GENERATING
            && $this->generating_since
            && $this->generating_since->lt(now()->subSeconds(max(1, $graceSeconds)));
    }

    public function fileExists(): bool
    {
        return $this->file_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }
}
