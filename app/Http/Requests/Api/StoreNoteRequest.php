<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'floor_number' => ['required', 'integer', 'min:0'],
            'camera_number' => ['required', 'integer', 'min:1'],
            'observed_at' => ['required', 'date'],
            // عبور منتصف الليل والمدى يعالجهما NoteService::normalizeObservedRange في الخدمة.
            'observed_end_at' => ['nullable', 'date'],
            // min:10 موحد مع واجهة الويب — كان مفقوداً في API فيقبل وصفاً من حرف واحد.
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
