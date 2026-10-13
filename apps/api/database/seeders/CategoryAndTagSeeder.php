<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

/**
 * Seeds the taxonomy tables from the CSV files beside this seeder.
 *
 * The CSVs are bootstrap data only. Once these rows exist they are the source
 * of truth: `PluginService` resolves submitted categories and tags against the
 * tables, so an admin adding a category here makes it usable without a deploy.
 */
class CategoryAndTagSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFrom('categories.csv', Category::query());
        $this->seedFrom('tags.csv', Tag::query());
    }

    /**
     * @param  Builder<Category|Tag>  $query
     */
    private function seedFrom(string $file, $query): void
    {
        $handle = fopen(database_path('data/'.$file), 'r');

        if ($handle === false) {
            throw new \RuntimeException("Unable to read taxonomy file [{$file}].");
        }

        fgetcsv($handle); // header

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (($row[0] ?? '') === '') {
                    continue;
                }

                $query->firstOrCreate(['name' => $row[0]]);
            }
        } finally {
            fclose($handle);
        }
    }
}
