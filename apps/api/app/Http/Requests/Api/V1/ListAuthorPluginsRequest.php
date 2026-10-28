<?php

namespace App\Http\Requests\Api\V1;

class ListAuthorPluginsRequest extends AuthorRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', config('plugins.pagination.default_per_page'));

        return min($perPage, (int) config('plugins.pagination.max_per_page'));
    }
}
