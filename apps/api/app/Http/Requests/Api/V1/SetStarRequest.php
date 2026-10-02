<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SetStarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `starred` is required rather than defaulting, because both defaults are a
     * silent wrong answer: defaulting to true would star plugins the caller never
     * asked for, and defaulting to false would quietly discard a star the user
     * already had. A missing field is a client bug and should say so.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starred' => ['required', 'boolean'],
        ];
    }
}
