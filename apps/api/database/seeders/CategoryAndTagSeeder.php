<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class CategoryAndTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('plugins.categories', []) as $category) {
            Category::query()->firstOrCreate(['name' => $category]);
        }

        foreach (config('plugins.tags', []) as $tag) {
            Tag::query()->firstOrCreate(['name' => $tag]);
        }
    }
}
