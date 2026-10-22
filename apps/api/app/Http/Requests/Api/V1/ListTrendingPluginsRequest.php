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
        ];
    }

    /**
     * Resolve the clamped items-per-page limit.
     */
    public function perPage(): int
    {
        $defaultPerPage = (int) config('plugins.trending_api.default_per_page', config('plugins.trending_api.default_limit'));
        $maxPerPage = (int) config('plugins.trending_api.max_per_page', config('plugins.trending_api.max_limit'));

        $perPage = (int) $this->query('per_page', $defaultPerPage);

        return max(1, min($perPage, $maxPerPage));
    }
}

