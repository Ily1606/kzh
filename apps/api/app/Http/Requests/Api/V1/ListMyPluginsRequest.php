<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PluginStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMyPluginsRequest extends FormRequest
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
            /*
             * Narrow the list to one review status. Omit it to get every status
             * the caller owns.
             */
            'status' => ['nullable', Rule::enum(PluginStatus::class)],

            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
