<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePluginRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'license' => ['sometimes', 'string', Rule::in(config('plugins.licenses', []))],
            'category' => ['sometimes', 'string', Rule::in(config('plugins.categories', []))],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', Rule::in(config('plugins.tags', []))],
            'source_link' => ['sometimes', 'string', 'url:https', 'max:2048'],
        ];
    }

    /**
     * Reject a request that would change nothing.
     *
     * With four `sometimes` rules an empty body validates cleanly and the
     * service would happily write an empty attribute set and answer 200 with
     * the plugin unchanged. That reads as a successful edit while nothing was
     * edited, which hides a client bug rather than reporting it. One error on
     * a single key reads better than `required_without_all` on all four,
     * which reports the same thing four times in four long sentences.
     *
     * Only runs once the rules above passed, so a request that is invalid for
     * another reason is not also told it is empty.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // `$this->all()` rather than `validated()`: `validated()` is not
            // populated yet inside `after()`. Intersecting against the
            // writable list is what makes an unknown-only body (`star_count`
            // alone) count as empty — it is sent by a client that thinks
            // these fields are editable, and answering 200 would confirm it.
            $editable = array_intersect_key($this->all(), array_flip([
                'name',
                'title',
                'license',
                'category',
                'tags',
                'source_link',
            ]));

            if ($editable === []) {
                $validator->errors()->add('plugin', __('api.plugin_update_requires_field'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'title', 'license', 'source_link'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }
}
