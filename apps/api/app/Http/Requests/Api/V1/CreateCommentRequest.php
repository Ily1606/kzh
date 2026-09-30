<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:2000'],
            'parent_comment_id' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }
}
