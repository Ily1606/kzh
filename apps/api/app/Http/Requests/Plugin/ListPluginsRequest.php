<?php

namespace App\Http\Requests\Plugin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListPluginsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = config('plugins.pagination.max_per_page');

        return [
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ];
    }
}
