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

    public function test_locking_a_user_stamps_locked_at_and_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');

        $repository = app(UserRepositoryInterface::class);

        $locked = $repository->lock($user);

        $this->assertNotNull($locked->locked_at);
        $this->assertNotNull($user->fresh()->locked_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_locking_an_already_locked_user_keeps_the_original_timestamp(): void
    {
        $original = now()->subWeek();

        $user = User::factory()->create(['locked_at' => $original]);

        $repository = app(UserRepositoryInterface::class);

        // Idempotent: a second lock must not rewrite history, so an admin can
        // still tell when the account was first locked.
        $this->assertSame(
            $original->format('Y-m-d H:i:s'),
            $repository->lock($user)->locked_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_unlocking_a_user_clears_locked_at_without_restoring_tokens(): void
    {
        $user = User::factory()->create();
        $repository = app(UserRepositoryInterface::class);

        $repository->lock($user);
        $user->createToken('api-token');

        $unlocked = $repository->unlock($user);

        $this->assertNull($unlocked->locked_at);
        $this->assertNull($user->fresh()->locked_at);

        // Unlock only lifts the block; it must not resurrect the tokens the
        // lock revoked, or a lock would be reversible by simply unlocking.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_locking_leaves_the_account_unusable_for_credential_lookup(): void
    {
        $user = User::factory()->create([
            'email' => 'target@example.com',
            'password' => 'secret-password',
        ]);

        $repository = app(UserRepositoryInterface::class);

        $this->assertNotNull($repository->findValidCredentials('target@example.com', 'secret-password'));

        $repository->lock($user);

        // The repository rule that guards the API login already keys off
        // `locked_at`; the admin action is what makes that state reachable.
        $this->assertNull($repository->findValidCredentials('target@example.com', 'secret-password'));
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
