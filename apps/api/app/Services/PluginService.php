<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class PluginService
{
    public function __construct(
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    /**
     * @param  array{name: string, title: string, license: string, source_link: string}  $attributes
     */
    public function submit(User $user, array $attributes): Plugin
    {
        try {
            return $this->pluginRepository->create([
                'name' => $attributes['name'],
                'title' => $attributes['title'],
                'license' => $attributes['license'],
                'source_link' => $attributes['source_link'],
                'user_id' => $user->getAuthIdentifier(),
                'status' => PluginStatus::Pending,
                'star_count' => 0,
                'comment_count' => 0,
                'view_count' => 0,
                'approved_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('api.plugin_name_already_exists'),
            ]);
        }
    }
}
