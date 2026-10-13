<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Tag;
use Database\Seeders\CategoryAndTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_seeded_categories(): void
    {
        $this->seed(CategoryAndTagSeeder::class);

        $response = $this->getJson('/api/v1/categories')->assertOk();

        $this->assertSame(
            Category::query()->orderBy('name')->pluck('name')->all(),
            $response->json('data.*.name'),
        );
        $response->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_it_lists_the_seeded_tags(): void
    {
        $this->seed(CategoryAndTagSeeder::class);

        $response = $this->getJson('/api/v1/tags')->assertOk();

        $this->assertSame(
            Tag::query()->orderBy('name')->pluck('name')->all(),
            $response->json('data.*.name'),
        );
        $response->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    /**
     * The point of moving the taxonomy out of config: a category added at
     * runtime shows up here without a deploy.
     */
    public function test_a_category_added_at_runtime_is_listed(): void
    {
        Category::query()->create(['name' => 'Cryptocurrency']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cryptocurrency');
    }

    public function test_it_answers_with_an_empty_list_before_anything_is_seeded(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/tags')->assertOk()->assertExactJson(['data' => []]);
    }
}
