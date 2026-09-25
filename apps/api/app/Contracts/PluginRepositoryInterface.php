<?php

namespace App\Contracts;

use App\Models\Plugin;

interface PluginRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plugin;
}
