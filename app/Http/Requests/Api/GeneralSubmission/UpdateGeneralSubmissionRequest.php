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
                            $fail('يجب أن يكون المستخدم كاتب تقارير');
                        }
                    }
                },
            ],
        ];
    }

    
    public function messages(): array
    {
        return [
            'report_writer_id.integer' => 'معرف الكاتب يجب أن يكون رقماً',
            'report_writer_id.exists' => 'الكاتب غير موجود',
        ];
    }
}
