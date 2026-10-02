<?php

namespace App\Http\Requests\Plugin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetTrendingPluginsRequest extends FormRequest
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
        $maxLimit = config('plugins.trending_api.max_limit');

        return [
            'limit' => ['nullable', 'integer', 'min:1', "max:{$maxLimit}"],
        ];
    }
}
