<?php

namespace App\Models;

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

    protected $fillable = [
        'name',
        'username',
        'personal_number',
        'password',
        'role',
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
        return mb_substr($this->name ?? $this->username ?? '?', 0, 1);
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
        $total = $this->notes()->count();
        if ($total === 0) return 0.0;
        $accepted = $this->notes()->where('status', 'accepted')->count();
        $rate = round(($accepted / max(1,$total)) * 5, 1);
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
        if ($r >= 4.5) return 'ممتاز';
        if ($r >= 3.5) return 'جيد جداً';
        if ($r >= 2.5) return 'جيد';
        if ($r >= 1.5) return 'مقبول';
        if ($r > 0) return 'ضعيف';
        return 'بدون تقييم';
    }
}
