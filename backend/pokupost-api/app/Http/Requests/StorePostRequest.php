<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:5000'],
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,mkv', 'max:512000'],
            'platforms' => ['nullable'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}