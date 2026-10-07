<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the lock/unlock actions on the admin user list at /admin/users.
 *
 * Both actions share one precondition worth naming: an admin may never act on
 * their own row, because the panel re-checks `canAccessPanel()` on the next
 * request and a self-lock would strand the session with no way back in.
 */
class UserListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_non_admins_cannot_open_the_user_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_admins_can_open_the_user_list_page(): void
    {
        $this->get('/admin/users')->assertOk();
    }

    public function test_both_actions_are_registered_on_the_table(): void
    {
        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            ->assertTableActionExists('lock')
            ->assertTableActionExists('unlock');
    }

    public function test_the_lock_action_is_hidden_for_a_locked_user(): void
    {
        $locked = User::factory()->create(['locked_at' => now()]);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('lock', $locked)
            ->assertTableActionVisible('unlock', $locked);
    }

    public function test_the_unlock_action_is_hidden_for_an_active_user(): void
    {
        $active = User::factory()->create(['locked_at' => null]);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('lock', $active)
            ->assertTableActionHidden('unlock', $active);
    }

    public function test_the_lock_action_is_hidden_on_the_admins_own_row(): void
    {
        $admin = auth()->user();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('lock', $admin)
            ->assertTableActionHidden('unlock', $admin);
    }

    public function test_a_locked_admin_cannot_see_the_lock_action_even_on_their_own_row(): void
    {
        $admin = auth()->user();
        $admin->update(['locked_at' => now()]);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('lock', $admin->fresh());
    }

    public function test_both_actions_are_hidden_on_a_soft_deleted_row(): void
    {
        $trashed = User::factory()->create();
        $trashed->delete();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('lock', $trashed)
            ->assertTableActionHidden('unlock', $trashed);
    }

    public function test_locking_a_user_stamps_locked_at_and_revokes_their_tokens(): void
    {
        $target = User::factory()->create();
        $target->createToken('api-token');

        Livewire::test(ListUsers::class)
            ->callTableAction('lock', $target)
            ->assertHasNoActionErrors();

        $this->assertNotNull($target->fresh()->locked_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unlocking_a_user_clears_locked_at(): void
    {
        $target = User::factory()->create(['locked_at' => now()]);

        Livewire::test(ListUsers::class)
            ->callTableAction('unlock', $target)
            ->assertHasNoActionErrors();

        $this->assertNull($target->fresh()->locked_at);
    }

    public function test_locking_a_user_leaves_them_out_of_the_active_filter(): void
    {
        $target = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('lock', $target)
            ->filterTable('status', 'active')
            ->assertCanNotSeeTableRecords([$target->fresh()]);

        Livewire::test(ListUsers::class)
            ->filterTable('status', 'locked')
            ->assertCanSeeTableRecords([$target->fresh()]);
    }

    public function test_the_status_badge_follows_the_action(): void
    {
        $target = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertSee(__('user.table.columns.status_options.active'));

        Livewire::test(ListUsers::class)
            ->callTableAction('lock', $target)
            ->assertSee(__('user.table.columns.status_options.locked'));
    }
}
