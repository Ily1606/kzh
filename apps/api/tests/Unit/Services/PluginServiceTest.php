<?php

namespace Tests\Unit\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Models\User;
use App\Services\PluginService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use Tests\TestCase;

class PluginServiceTest extends TestCase
{
    public function test_duplicate_database_constraint_is_exposed_as_a_name_validation_error(): void
    {
        $repository = Mockery::mock(PluginRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'sqlite',
                'insert into plugins',
                [],
                new PDOException('UNIQUE constraint failed: plugins.user_id, plugins.name'),
            ));

        $this->expectException(ValidationException::class);

        try {
            app(PluginService::class, ['pluginRepository' => $repository])
                ->submit(User::factory()->make(), [
                    'name' => 'Laravel Debugbar',
                    'title' => 'Debugbar for Laravel',
                    'license' => 'MIT',
                    'source_link' => 'https://github.com/barryvdh/laravel-debugbar',
                ]);
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['You already submitted a plugin with this name.'],
                $exception->errors()['name'],
            );

            throw $exception;
        }
    }
}
