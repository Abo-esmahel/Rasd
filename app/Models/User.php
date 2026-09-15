<?php

namespace App\Models;

use App\Services\Localization\NameTransliterationService;
use App\Support\SyrianPhone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (!empty($user->name) && empty($user->name_en) && empty($user->name_ar)) {
                $translit = app(NameTransliterationService::class);
                if ($translit->isArabic($user->name)) {
                    $user->name_ar = $user->name;
                    $user->name_en = $translit->generateLatinName($user->name);
                } else {
                    $user->name_en = $user->name;
                    $user->name_ar = $translit->generateArabicName($user->name);
                }
            }
        });

        static::updating(function (User $user) {
            $originalName = $user->getOriginal('name');
            if (!empty($user->name) && $user->name !== $originalName) {
                $translit = app(NameTransliterationService::class);
                if ($translit->isArabic($user->name)) {
                    $user->name_ar = $user->name;
                    $user->name_en = $translit->generateLatinName($user->name);
                } else {
                    $user->name_en = $user->name;
                    $user->name_ar = $translit->generateArabicName($user->name);
                }
            }
        });
    }

    protected $fillable = [
        'name',
        'name_en',
        'name_ar',
        'username',
        'personal_number',
        'password',
        'role',
        'locale',
        'avatar_path',
    ];

    protected $appends = ['avatar_url', 'whatsapp_number', 'whatsapp_url'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function processedNotes(): HasMany
    {
        return $this->hasMany(Note::class, 'processed_by');
    }

    public function isMonitor(): bool
    {
        return $this->role === 'monitor';
    }

    public function isReportWriter(): bool
    {
        return $this->role === 'report_writer';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->avatar_path)) {
            return '/storage/'.ltrim($this->avatar_path, '/');
        }

        return null;
    }

    public function getInitialAttribute(): string
    {
        return mb_substr($this->localized_name ?? $this->username ?? '?', 0, 1);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en') {
            return $this->name_en ?? $this->name;
        }

        return $this->name_ar ?? $this->name;
    }

    public function getLocalizedNameUrlAttribute(): string
    {
        $name = $this->localized_name;
        return preg_replace('/\s+/', '-', trim($name));
    }

    protected function personalNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? (SyrianPhone::normalize($value) ?? $value) : null,
            set: fn (?string $value) => $value === null || trim($value) === '' ? null : SyrianPhone::normalize($value),
        );
    }

    public function getWhatsappNumberAttribute(): ?string
    {
        return SyrianPhone::toWhatsapp($this->personal_number);
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return SyrianPhone::whatsappUrl($this->personal_number);
    }

    public function getRatingAttribute(): float
    {
        if (!$this->isMonitor()) return 0.0;
        $total = $this->attributes['total_notes'] ?? $this->notes()->count();
        if ($total === 0) return 0.0;
        $accepted = $this->attributes['accepted_notes'] ?? $this->notes()->where('status', 'accepted')->count();
        $rate = round(($accepted / max(1, $total)) * 5, 1);
        return $rate;
    }

    public function getRatingStarsAttribute(): string
    {
        $r = $this->rating;
        $full = floor($r);
        $half = ($r - $full) >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;
        return str_repeat('★', $full) . ($half ? '⯪' : '') . str_repeat('☆', $empty);
    }

    public function getRatingLabelAttribute(): string
    {
        $r = $this->rating;
        if ($r >= 4.5) return __('ui.rating_excellent');
        if ($r >= 3.5) return __('ui.rating_very_good');
        if ($r >= 2.5) return __('ui.rating_good');
        if ($r >= 1.5) return __('ui.rating_fair');
        if ($r > 0) return __('ui.rating_weak');
        return __('ui.rating_none');
    }
}
