<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:5000'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string'],
            'status' => ['nullable', 'in:draft,queued,published,failed'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}