<?php

namespace App\Contracts;

use App\Models\Tag;

interface TagRepositoryInterface
{
    /**
     * The tags with these names, keyed by name.
     *
     * Fewer results than names asked for means some of them do not exist.
     *
     * @param  array<int, string>  $names
     * @return array<string, Tag>
     */
    public function findManyByName(array $names): array;
}
