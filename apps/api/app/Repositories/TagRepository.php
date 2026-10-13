<?php

namespace App\Repositories;

use App\Contracts\TagRepositoryInterface;
use App\Models\Tag;

/**
 * @extends BaseRepository<Tag>
 */
final class TagRepository extends BaseRepository implements TagRepositoryInterface
{
    public function getModel(): string
    {
        return Tag::class;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, Tag>
     */
    public function findManyByName(array $names): array
    {
        // No names means nothing to look up: return before touching the
        // database so an empty tag list costs no query at all.
        if ($names === []) {
            return [];
        }

        return $this->newQuery()
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name')
            ->all();
    }
}
