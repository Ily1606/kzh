<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListTrendingPluginsRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Resolve the items-per-page count.
     */
    public function perPage(): int
    {
        $defaultPerPage = (int) config('plugins.trending.default_per_page', config('plugins.trending.per_page', 15));

        $perPage = (int) ($this->query('per_page') ?? $this->query('limit') ?? $defaultPerPage);

        return max(1, $perPage);
    }
}

