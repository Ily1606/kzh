<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'license' => ['required', 'string', Rule::in(config('plugins.licenses', []))],
            'source_link' => ['required', 'string', 'url:https', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'title', 'license', 'source_link'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }
}
