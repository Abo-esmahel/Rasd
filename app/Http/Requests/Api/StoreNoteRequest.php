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
            'observed_end_at' => ['nullable', 'date', 'after_or_equal:observed_at'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
