<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => [
                'nullable',
                'string',
                'max:2200',
            ],

            'media' => [
                'required',
                'array',
                'min:1',
            ],

            'media.*.type' => [
                'required',
                'in:image,video',
            ],

            'media.*.file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov',
                'max:102400',
            ],

            'destinations' => [
                'required',
                'array',
                'min:1',
            ],

            'destinations.*.social_account_id' => [
                'required',
                'integer',
                'exists:social_accounts,id',
            ],

            'destinations.*.scheduled_at' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
        ];
    }
}