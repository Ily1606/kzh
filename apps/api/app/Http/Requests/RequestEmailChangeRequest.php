<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'current_password' => ['required'],
            'new_email' => [
                'required',
                'email',
                Rule::notIn([$user->email]),
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ];
    }
}

