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
            (int) config('attachments.max_image_size', 20480),
            (int) config('attachments.max_video_size', 102400)
        );

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxSize,
                'mimes:jpg,jpeg,png,webp,heic,heif,tiff,tif,bmp,avif,gif,svg,mp4,webm,mov,avi,3gp,3gpp,mkv,m4v,mpg,mpeg,wmv,flv,ogv,ts,mts,m2ts,vob,asf,m2v,3g2,f4v,m4p,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,ra,weba,ac3,dts,alac',
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
