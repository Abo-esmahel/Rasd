<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (trim((string) $this->input('title', '')) === '') {
            $this->merge(['title' => __('report.daily_title_default', ['date' => $this->input('report_date', now()->toDateString())])]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'content' => ['nullable', 'string', 'max:20000'],
            'visible_to_monitors' => ['nullable', 'boolean'],
        ];
    }
}
