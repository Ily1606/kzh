<?php

namespace App\Repositories;

use App\Contracts\CategoryRepositoryInterface;
use App\Models\Category;

/**
 * @extends BaseRepository<Category>
 */
final class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function findByName(string $name): ?Category
    {
        return $this->newQuery()->where('name', $name)->first();
    }
}
