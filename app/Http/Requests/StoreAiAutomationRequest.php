<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],

            'niche' => [
                'required',
                'string',
                'max:255',
            ],

            'topics' => [
                'required',
                'array',
                'min:1',
            ],

            'topics.*' => [
                'required',
                'string',
                'max:255',
            ],

            'tone' => [
                'required',
                'string',
                'max:100',
            ],

            'posting_times' => [
                'required',
                'array',
                'min:1',
                'max:24',
            ],

            'posting_times.*' => [
                'required',
                'date_format:H:i',
                'distinct',
            ],

            'platforms' => [
                'required',
                'array',
                'min:1',
            ],

            'platforms.*' => [
                'required',
                'string',
                Rule::in([
                    'facebook',
                    'instagram',
                    'linkedin',
                    'tiktok',
                ]),
                'distinct',
            ],

            'auto_publish' => [
                'sometimes',
                'boolean',
            ],

            'timezone' => [
                'required',
                'timezone',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled', true),
            'auto_publish' => $this->boolean('auto_publish', true),
        ]);
    }
}