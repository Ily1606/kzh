<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPluginEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', Rule::in(['newest', 'oldest'])],
        ];
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', config('plugins.events.default_per_page'));

        return min($perPage, (int) config('plugins.events.max_per_page'));
    }

    public function sort(): string
    {
        return $this->validated('sort', 'newest');
    }
}
