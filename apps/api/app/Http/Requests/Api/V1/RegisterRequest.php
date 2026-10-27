<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::whereNull('deleted_at')
                        ->whereRaw('LOWER(email) = ?', [Str::lower($value)])
                        ->exists()
                    ) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
