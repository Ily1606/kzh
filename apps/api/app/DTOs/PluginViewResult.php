<?php

namespace App\DTOs;

final class PluginViewResult
{
    public readonly string $status;

    public function __construct(
        public readonly bool $counted,
        public readonly int $viewCount,
        public readonly string $message = '',
    ) {
        $this->status = $this->counted ? 'success' : 'ignored';
    }
}
