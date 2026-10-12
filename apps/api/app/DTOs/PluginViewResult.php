<?php

namespace App\DTOs;

use ArrayAccess;
use LogicException;

final class PluginViewResult implements ArrayAccess
{
    public function __construct(
        public readonly bool $counted,
        public readonly int $viewCount,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['counted', 'view_count', 'status'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'counted' => $this->counted,
            'view_count' => $this->viewCount,
            'status' => $this->counted ? 'success' : 'ignored',
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('PluginViewResult is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('PluginViewResult is immutable');
    }
}
