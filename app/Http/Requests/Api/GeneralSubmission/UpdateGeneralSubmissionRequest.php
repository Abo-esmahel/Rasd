<?php

namespace App\Http\Requests\Api\GeneralSubmission;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSubmissionRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
            'report_writer_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        $writer = User::find($value);
                        if (!$writer || !$writer->isReportWriter()) {
                            $fail(__('api.validation_user_must_writer'));
                        }
                    }
                },
            ],
        ];
    }

    
    public function messages(): array
    {
        return [
            'report_writer_id.integer' => __('api.validation_writer_id_integer'),
            'report_writer_id.exists' => __('api.validation_writer_not_found'),
        ];
    }
}
