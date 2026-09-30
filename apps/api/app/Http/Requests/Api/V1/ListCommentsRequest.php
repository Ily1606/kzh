<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-string validation shared by the comment list and the replies endpoint.
 *
 * Both endpoints take the same knobs, so both use this request and cannot drift
 * apart on clamping rules.
 */
class ListCommentsRequest extends FormRequest
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
            // `per_page` is intentionally not capped by a `max` rule: an oversized
            // value is clamped in perPage() instead of being rejected, the same way
            // PluginController handles its own `per_page`.
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', Rule::in((array) config('comments.sorts', []))],
        ];
    }

    /**
     * Page size to paginate with, always inside the configured bounds.
     */
    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', config('comments.pagination.default_per_page'));

        return min(max($perPage, 1), (int) config('comments.pagination.max_per_page'));
    }

    /**
     * Sort strategy to order by, falling back to the configured default.
     */
    public function sort(): string
    {
        $sort = (string) $this->validated('sort', config('comments.default_sort', 'newest'));

        return in_array($sort, (array) config('comments.sorts', []), true)
            ? $sort
            : 'newest';
    }
}
