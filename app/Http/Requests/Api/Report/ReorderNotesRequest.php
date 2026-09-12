<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReorderNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1', 'max:500'],
            'ordered_ids.*' => ['integer', 'distinct', 'exists:notes,id'],
        ];
    }
}
