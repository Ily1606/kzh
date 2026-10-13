<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_users_can_access_the_admin_panel(): void
    {
        $panel = Panel::make()->id('admin');
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertInstanceOf(FilamentUser::class, $user);
        $this->assertFalse($user->canAccessPanel($panel));
        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertIsBool($admin->is_admin);
    }

    public function test_a_locked_admin_is_denied_panel_access(): void
    {
        $panel = Panel::make()->id('admin');

        $admin = User::factory()->create(['is_admin' => true]);
        $admin->update(['locked_at' => now()]);

        $this->assertFalse($admin->fresh()->canAccessPanel($panel));
    }

    public function test_a_soft_deleted_admin_is_denied_panel_access(): void
    {
        $panel = Panel::make()->id('admin');

        $admin = User::factory()->create(['is_admin' => true]);
        $admin->delete();

        $this->assertFalse($admin->fresh()->canAccessPanel($panel));
    }

    public function test_admin_route_enforces_the_user_access_gate(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }
}
