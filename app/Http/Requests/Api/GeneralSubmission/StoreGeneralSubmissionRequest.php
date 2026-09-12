<?php

namespace App\Http\Requests\Api\GeneralSubmission;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreGeneralSubmissionRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        $maxFiles = min(max(1, (int) ini_get('max_file_uploads') ?: 20), (int) config('attachments.max_per_submission', 5));
        $fileMaxKb = \App\Services\NoteService::uploadFileMaxKb();

        return [
            'floor_number' => ['required', 'integer', 'min:0'],
            'camera_number' => ['required', 'integer', 'min:1'],
            'observed_at' => ['required', 'date'],
            // عبور منتصف الليل والمدى يعالجهما NoteService::normalizeObservedRange في الخدمة.
            'observed_end_at' => ['nullable', 'date'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$fileMaxKb],
            'client_files_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_writer_ids' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    
                    if (count($value) !== count(array_unique($value))) {
                        $fail(__('api.validation_recipients_unique'));
                    }
                    
                    $validWriters = User::whereIn('id', $value)->where('role', 'report_writer')->pluck('id');
                    $invalidIds = array_diff($value, $validWriters->toArray());
                    if (!empty($invalidIds)) {
                        $fail(__('api.validation_recipients_writers'));
                    }
                },
            ],
        ];
    }

    
    public function messages(): array
    {
        return [
            'floor_number.required' => __('validation.required', ['attribute' => __('validation.attributes.floor_number')]),
            'camera_number.required' => __('validation.required', ['attribute' => __('validation.attributes.camera_number')]),
            'observed_at.required' => __('validation.required', ['attribute' => __('validation.attributes.observed_at')]),
            'description.required' => __('validation.required', ['attribute' => __('validation.attributes.description')]),
            'report_writer_ids.required' => __('api.sub_need_writer'),
            'report_writer_ids.array' => __('validation.array', ['attribute' => __('validation.attributes.report_writer_ids')]),
            'report_writer_ids.min' => __('api.sub_need_writer'),
        ];
    }
}
