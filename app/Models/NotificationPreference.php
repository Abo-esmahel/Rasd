<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'sound_enabled',
        'desktop_enabled',
        'volume',
        'toast_enabled',
        'sound_theme',
        'muted_types',
    ];

    protected $casts = [
        'sound_enabled' => 'boolean',
        'desktop_enabled' => 'boolean',
        'toast_enabled' => 'boolean',
        'volume' => 'integer',
        'muted_types' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'sound_enabled' => true,
                'desktop_enabled' => true,
                'volume' => 70,
                'toast_enabled' => true,
                'sound_theme' => 'default',
                'muted_types' => [],
            ]
        );
    }
}
