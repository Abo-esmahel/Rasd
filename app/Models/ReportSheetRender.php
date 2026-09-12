<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSheetRender extends Model
{
    protected $fillable = [
        'report_id',
        'generation_no',
        'data_version',
        'template',
        'payload_hash',
        'system_hash',
        'payload',
        'image_path',
    ];

    protected $casts = [
        'generation_no' => 'integer',
        'data_version' => 'integer',
        'payload' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function generationId(): string
    {
        return "gen_{$this->report_id}_{$this->generation_no}";
    }
}
