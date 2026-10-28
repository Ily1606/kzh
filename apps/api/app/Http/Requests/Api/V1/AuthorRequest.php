<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Support\UsernameHelper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The username is a route param: merge it into the input to validate it.
        $this->merge(['username' => (string) $this->route('username')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** @ignoreParam */
            'username' => [
            'required',
            'string',
            'max:39',
            'regex:'.UsernameHelper::PATTERN,
            // Prevent username that looks like a UUID to avoid route conflicts
            // with /plugins/{pluginId}/comments and /plugins/{pluginId}/events
            'not_regex:'.UsernameHelper::UUID_PATTERN,
        ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Only convert to 404 if the validation error is on the username field
        $failedFields = $validator->failed();

        if (isset($failedFields['username'])) {
            throw (new ModelNotFoundException)->setModel(User::class, [$this->route('username')]);
        }

        // For other fields (page, per_page, etc.), throw ValidationException for 422
        throw new ValidationException($validator);
    }
}
