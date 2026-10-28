<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAutomationRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'topics' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'posting_times' => [
                'sometimes',
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
                'sometimes',
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
                'sometimes',
                'required',
                'timezone',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enabled')) {
            $this->merge([
                'enabled' => $this->boolean('enabled'),
            ]);
        }

        if ($this->has('auto_publish')) {
            $this->merge([
                'auto_publish' => $this->boolean('auto_publish'),
            ]);
        }
    }
}