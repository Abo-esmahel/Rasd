<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'field',
        'source_hash',
        'source_text',
        'locale',
        'translated_text',
    ];
}
