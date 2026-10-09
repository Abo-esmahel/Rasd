<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ReportComment extends Model
{
    protected $fillable = ['report_id', 'user_id', 'parent_id', 'body'];

    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->with(['user', 'reactions']); }
    public function reactions(): MorphMany { return $this->morphMany(Reaction::class, 'reactable'); }
}
