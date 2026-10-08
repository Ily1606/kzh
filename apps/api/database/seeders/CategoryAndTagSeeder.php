<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class CategoryAndTagSeeder extends Seeder
{
    public function run(): void
    {
        $categories = config('plugins.categories', []);
        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category]);
        }

        $tags = config('plugins.tags', []);
        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag]);
        }
    }
}
