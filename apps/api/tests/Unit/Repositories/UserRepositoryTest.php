<?php

namespace Tests\Unit\Repositories;

use App\Contracts\AuthRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\AuthRepository;
use App\Repositories\BaseRepository;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_children_declare_their_model_through_the_hook(): void
    {
        $this->assertSame(User::class, app(UserRepository::class)->getModel());
        $this->assertSame(User::class, app(AuthRepository::class)->getModel());
    }

    public function test_base_repository_resolves_the_model_of_the_calling_child(): void
    {
        // The base class never names a model: it asks via getModel().
        $this->assertInstanceOf(User::class, app(UserRepository::class)->resetModel());
        $this->assertInstanceOf(User::class, app(AuthRepository::class)->resetModel());
    }

    public function test_reset_model_returns_a_fresh_instance_every_time(): void
    {
        $repository = app(UserRepository::class);

        $this->assertNotSame($repository->resetModel(), $repository->resetModel());
    }

    public function test_shared_crud_from_the_base_class_persists_the_child_model(): void
    {
        $repository = app(UserRepository::class);

        $user = $repository->create([
            'name' => 'Nguyen Van A',
            'email' => 'nguyen@example.com',
            'password' => 'secret-password',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($user->id, $repository->find($user->id)?->id);
        $this->assertDatabaseHas('users', ['email' => 'nguyen@example.com']);

        $repository->update($user, ['name' => 'Tran Van B']);

        $this->assertSame('Tran Van B', $user->refresh()->name);
    }

    public function test_find_by_email_returns_the_matching_account(): void
    {
        User::factory()->create(['email' => 'active@example.com']);

        $repository = app(UserRepository::class);

        $this->assertSame('active@example.com', $repository->findByEmail('active@example.com')?->email);
        $this->assertNull($repository->findByEmail('missing@example.com'));
    }

    public function test_credential_rule_is_enforced_in_the_child_not_the_base(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'secret-password',
        ]);
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'secret-password',
            'locked_at' => now(),
        ]);

        $repository = app(UserRepository::class);

        $this->assertNotNull($repository->findValidCredentials('active@example.com', 'secret-password'));
        $this->assertNull($repository->findValidCredentials('active@example.com', 'wrong-password'));
        $this->assertNull($repository->findValidCredentials('locked@example.com', 'secret-password'));
        $this->assertNull($repository->findValidCredentials('missing@example.com', 'secret-password'));
    }

    public function test_base_repository_is_abstract_and_cannot_be_instantiated(): void
    {
        $this->assertTrue((new ReflectionClass(BaseRepository::class))->isAbstract());
    }

    public function test_auth_repository_issues_and_revokes_tokens_for_a_user(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $repository = app(AuthRepositoryInterface::class);

        $token = $repository->createToken($user);

        $this->assertSame('api-token', $token->accessToken->name);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->assertSame(1, $repository->revokeTokens($user));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_repositories_are_bound_to_their_contracts(): void
    {
        $this->assertInstanceOf(UserRepository::class, app(UserRepositoryInterface::class));
        $this->assertInstanceOf(AuthRepository::class, app(AuthRepositoryInterface::class));
    }
}
