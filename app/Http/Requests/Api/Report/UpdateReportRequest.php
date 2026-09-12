<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'report_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'content' => ['nullable', 'string', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:20000'],
            'visible_to_monitors' => ['nullable', 'boolean'],
        ];
    }
}
