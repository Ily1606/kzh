<?php

namespace App\Http\Requests;

use App\Support\UsernameHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->username)) {
            $this->merge(['username' => Str::lower($this->username)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:39',
                'regex:'.UsernameHelper::PATTERN,
                // Prevent username that looks like a UUID to avoid route conflicts
                // with /plugins/{pluginId}/comments and /plugins/{pluginId}/events
                'not_regex:'.UsernameHelper::UUID_PATTERN,
                // Excludes the user's own row so they can keep their username.
                Rule::unique('users', 'username')->ignore($this->user()?->getKey()),
            ],
            'githubLink' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }
}
