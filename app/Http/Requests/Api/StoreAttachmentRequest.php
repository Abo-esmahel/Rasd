<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = max(
            (int) config('attachments.max_image_size', 50120),
            (int) config('attachments.max_video_size', 3990720)
        );

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxSize,
                'mimes:jpg,jpeg,png,webp,mp4,webm,mov,avi,3gp,mkv,m4v,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,weba',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'يجب اختيار ملف مرفق',
            'file.max' => 'حجم الملف يتجاوز الحد الأقصى المسموح',
            'file.mimes' => 'امتداد الملف غير مدعوم',
        ];
    }
}
