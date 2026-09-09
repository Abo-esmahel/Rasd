<?php

namespace App\Http\Requests\Api\GeneralSubmission;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreGeneralSubmissionRequest extends FormRequest
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
        $maxFiles = max(1, (int) ini_get('max_file_uploads') ?: 20);

        return [
            'floor_number' => ['required', 'integer', 'min:0'],
            'camera_number' => ['required', 'integer', 'min:1'],
            'observed_at' => ['required', 'date'],
            'observed_end_at' => ['nullable', 'date', 'after_or_equal:observed_at'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:512000'],
            'client_files_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_writer_ids' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Check for duplicates
                    if (count($value) !== count(array_unique($value))) {
                        $fail('لا يمكن وجود مستلمين مكررين');
                    }
                    // Check that all IDs exist and are report writers
                    $validWriters = User::whereIn('id', $value)->where('role', 'report_writer')->pluck('id');
                    $invalidIds = array_diff($value, $validWriters->toArray());
                    if (!empty($invalidIds)) {
                        $fail('يجب أن يكون جميع المستلمين من كتاب التقارير');
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
            'floor_number.required' => 'رقم الطابق مطلوب',
            'camera_number.required' => 'رقم الكاميرا مطلوب',
            'observed_at.required' => 'وقت الملاحظة مطلوب',
            'description.required' => 'الوصف مطلوب',
            'report_writer_ids.required' => 'يجب اختيار كاتب على الأقل',
            'report_writer_ids.array' => 'يجب أن تكون قائمة المستلمين',
            'report_writer_ids.min' => 'يجب اختيار كاتب على الأقل',
        ];
    }
}
