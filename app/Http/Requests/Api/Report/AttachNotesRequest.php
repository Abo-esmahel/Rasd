<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class AttachNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_ids' => ['required', 'array', 'min:1', 'max:500'],
            'note_ids.*' => ['integer', 'distinct', 'exists:notes,id'],
        ];
    }
}
