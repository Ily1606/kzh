<?php

namespace App\DTOs;

final class PluginViewResult
{
    public function __construct(
        public readonly bool $counted,
        public readonly int $viewCount,
    ) {}
}
