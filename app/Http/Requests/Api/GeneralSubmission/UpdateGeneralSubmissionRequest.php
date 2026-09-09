<?php

namespace App\Http\Requests\Api\GeneralSubmission;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
                            $fail('يجب أن يكون المستخدم كاتب تقارير');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_writer_id.integer' => 'معرف الكاتب يجب أن يكون رقماً',
            'report_writer_id.exists' => 'الكاتب غير موجود',
        ];
    }
}
