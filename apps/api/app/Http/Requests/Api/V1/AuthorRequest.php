<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Support\UsernameHelper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

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
            'username' => ['required', 'string', 'max:39', 'regex:'.UsernameHelper::PATTERN],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw (new ModelNotFoundException)->setModel(User::class, [$this->route('username')]);
    }
}
