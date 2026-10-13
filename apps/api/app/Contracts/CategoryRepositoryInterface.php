<?php

namespace App\Contracts;

use App\Models\Category;

interface CategoryRepositoryInterface
{
    /**
     * The category with this name, or null when there is no such row.
     */
    public function findByName(string $name): ?Category;
}
