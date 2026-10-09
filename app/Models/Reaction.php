<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    public const TYPES = ['like', 'love', 'insightful', 'thanks', 'dislike', 'angry'];

    protected $fillable = ['user_id', 'reaction'];
    public function reactable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
