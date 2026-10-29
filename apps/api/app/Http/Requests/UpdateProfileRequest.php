<?php

namespace App\Http\Requests;

use App\Models\UsernameHistory;
use App\Support\UsernameHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->username)) {
            $this->merge(['username' => Str::lower($this->username)]);
        }
    }

    public function rules(): array
    {
        $currentUserId = $this->user()?->getKey();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:39',
                'regex:'.UsernameHelper::PATTERN,
                'not_regex:'.UsernameHelper::UUID_PATTERN,
                // Reserved usernames từ config
                Rule::notIn(config('users.reserved_usernames', [])),
                // Kiểm tra unique trong bảng users (trừ user hiện tại)
                Rule::unique('users', 'username')->ignore($currentUserId),
                // Kiểm tra username history - chặn nếu đang trong thời gian khóa
                // Ngoại lệ: user hiện tại được lấy lại username của chính mình
                function ($attribute, $value, $fail) use ($currentUserId) {
                    $reservedRecord = UsernameHistory::byUsername($value)
                        ->stillReserved()
                        ->where('user_id', '!=', $currentUserId)
                        ->first();

                    if ($reservedRecord) {
                        $daysLeft = now()->diffInDays($reservedRecord->available_at, false) + 1;
                        $fail(__('validation.username_reserved', ['days' => $daysLeft]));
                    }
                },
            ],
            'githubLink' => ['sometimes', 'nullable', 'url:https', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.not_in' => __('validation.username_reserved_system'),
            'username.unique' => __('api.username_taken'),
        ];
    }
}