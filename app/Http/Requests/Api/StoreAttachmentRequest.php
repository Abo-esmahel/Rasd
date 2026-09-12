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
        // موحد مع باقي المسارات ومع حد PHP — كان يتجاهل حد الصوت وحد الخادم.
        $maxSize = \App\Services\NoteService::uploadFileMaxKb();

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
            'file.required' => __('validation.required', ['attribute' => __('validation.attributes.file')]),
            'file.max' => __('validation.max.file', ['attribute' => __('validation.attributes.file'), 'max' => ':max']),
            'file.mimes' => __('validation.mimes', ['attribute' => __('validation.attributes.file')]),
        ];
    }
}
