<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The timeline is the owner's private record of how their submission was
 * handled. Every test here is phrased around what a caller can actually learn
 * from the endpoint, because that is the property being protected: an
 * outsider must not be able to read another publisher's review history, and a
 * 403 would leak that the plugin exists even where a 404 leaks nothing.
 */
class ListPluginEventsTest extends TestCase
{
    use RefreshDatabase;

    private function ownedBy(User $owner): Plugin
    {
        return Plugin::factory()->create(['user_id' => $owner->id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payload(object $response): array
    {
        return $response->json('data.events');
    }

    public function test_the_owner_reads_their_own_timeline(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()->created()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.events.0.event_type', PluginEventType::Created->value)
            ->assertJsonPath('data.events.0.message', null);
    }

    public function test_the_admin_message_is_shown_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()
            ->byAdmin($admin, PluginEventType::UpdateRequested, 'Please document the config option.')
            ->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertOk()
            ->assertJsonPath('data.events.0.message', 'Please document the config option.');
    }

    /**
     * The point of the feature: the owner finds out what the admin did. An
     * approval is the outcome the owner most needs to see, and it is the one
     * carrying no message — so the event has to exist on its own.
     */
    public function test_an_approval_appears_even_though_it_carries_no_message(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()
            ->byAdmin($admin, PluginEventType::Approved)
            ->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertOk()
            ->assertJsonPath('data.events.0.event_type', PluginEventType::Approved->value)
            ->assertJsonPath('data.events.0.message', null);
    }

    public function test_a_guest_is_rejected(): void
    {
        $plugin = $this->ownedBy(User::factory()->create());

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    /**
     * Someone else's plugin must be indistinguishable from one that does not
     * exist. A 403 would confirm both that the plugin is real and that it sits
     * in review — exactly what a competitor would want to know.
     */
    public function test_another_users_plugin_is_a_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

    /**
     * Approved plugins are public, so the 404 here is carrying a different
     * weight: the plugin is visible in the catalogue to anyone, and the only
     * thing being withheld is the owner's private history. A 403 would say so
     * plainly instead.
     */
    public function test_an_approved_plugin_of_another_user_is_still_a_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        PluginEvent::factory()->created()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")->assertNotFound();
    }

    public function test_a_missing_plugin_is_a_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000/events')
            ->assertNotFound();
    }

    public function test_events_come_back_newest_first(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        // Fixed timestamps rather than now()-based ones: the column is a plain
        // `timestamp`, so it stores whole seconds and would silently drop the
        // microseconds a relative `now()` carries.
        PluginEvent::factory()->created()->create([
            'plugin_id' => $plugin->id,
            'created_at' => '2026-10-01 09:00:00',
        ]);
        PluginEvent::factory()->resubmitted()->create([
            'plugin_id' => $plugin->id,
            'created_at' => '2026-10-05 09:00:00',
        ]);
        PluginEvent::factory()->created()->create([
            'plugin_id' => $plugin->id,
            'created_at' => '2026-10-09 09:00:00',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertOk()
            ->assertJsonCount(3, 'data.events')
            ->assertJsonPath('data.events.0.created_at', '2026-10-09T09:00:00.000000Z')
            ->assertJsonPath('data.events.2.created_at', '2026-10-01T09:00:00.000000Z');
    }

    /**
     * `created_at` stores whole seconds, so events written inside one second
     * genuinely tie. SQL promises nothing about the order of equal values, so
     * without a tiebreak the database answers with whatever order its plan
     * found — which is stable on a small table and not stable on a large one.
     *
     * The ids are assigned so that id order is the *reverse* of insertion
     * order. That is what makes this test able to fail: a row order driven by
     * insertion gives one answer, an order driven by the tiebreak gives the
     * opposite one, and only the tiebreak is the intended behaviour.
     */
    public function test_events_sharing_a_timestamp_are_ordered_by_id(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        // Descending ids, inserted in ascending order — so insertion order and
        // id order cannot both be right.
        $ids = [
            '00000000-0000-4000-8000-000000000003',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000001',
        ];

        foreach ($ids as $id) {
            PluginEvent::factory()->resubmitted()->create([
                'id' => $id,
                'plugin_id' => $plugin->id,
                'created_at' => '2026-10-09 09:00:00',
            ]);
        }

        Sanctum::actingAs($owner);

        $returned = array_column($this->payload($this->getJson("/api/v1/plugins/{$plugin->id}/events")), 'id');

        $this->assertSame(
            $ids,
            $returned,
            'Ties must fall back to the id, not to whichever order the database happened to use.',
        );
    }

    /**
     * The tiebreak is part of the ordering, so the oldest-first view has to
     * reverse it too rather than only reversing the timestamp.
     */
    public function test_sort_oldest_also_orders_tied_events_by_id(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        $ids = [
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        ];

        foreach ($ids as $id) {
            PluginEvent::factory()->resubmitted()->create([
                'id' => $id,
                'plugin_id' => $plugin->id,
                'created_at' => '2026-10-09 09:00:00',
            ]);
        }

        Sanctum::actingAs($owner);

        $returned = array_column(
            $this->payload($this->getJson("/api/v1/plugins/{$plugin->id}/events?sort=oldest")),
            'id',
        );

        $this->assertSame($ids, $returned, 'Reversing the direction must reverse the tiebreak as well.');
    }

    /**
     * Paging a tied timeline: every event has to appear exactly once. This is
     * the failure a reviewer would actually see — an event showing up on two
     * pages, or not on any.
     */
    public function test_paging_across_tied_events_never_repeats_or_drops_one(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        foreach (range(1, 5) as $ignored) {
            PluginEvent::factory()->resubmitted()->create([
                'plugin_id' => $plugin->id,
                'created_at' => '2026-10-09 09:00:00',
            ]);
        }

        Sanctum::actingAs($owner);

        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $ids = array_merge(
                $ids,
                array_column($this->payload($this->getJson("/api/v1/plugins/{$plugin->id}/events?per_page=2&page={$page}")), 'id'),
            );
        }

        $this->assertCount(5, $ids, 'Paging a tied timeline must return every event.');
        $this->assertCount(5, array_unique($ids), 'A repeated id across pages is an unstable sort leaking into the timeline.');
    }

    public function test_sort_oldest_reverses_the_timeline(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()->created()->create([
            'plugin_id' => $plugin->id,
            'created_at' => now()->subDays(3),
        ]);
        PluginEvent::factory()->resubmitted()->create([
            'plugin_id' => $plugin->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events?sort=oldest")
            ->assertOk()
            ->assertJsonPath('data.events.0.event_type', PluginEventType::Created->value)
            ->assertJsonPath('data.events.1.event_type', PluginEventType::Resubmitted->value);
    }

    /**
     * Rejected rather than clamped: the sort knob has two valid values, and
     * silently reversing the owner's timeline because of a typo is worse than
     * telling them the value was wrong.
     */
    public function test_an_unknown_sort_is_rejected(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events?sort=sideways")
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_per_page_is_clamped_rather_than_rejected(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()->count(3)->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events?per_page=9999")
            ->assertOk()
            ->assertJsonPath('meta.per_page', config('plugins.events.max_per_page'));
    }

    public function test_a_zero_per_page_is_rejected(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events?per_page=0")
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_the_page_reports_its_state(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        PluginEvent::factory()->count(3)->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.current_page', 1);
    }

    /**
     * A plugin submitted before this feature existed has no events at all. The
     * empty timeline is the answer, not an error.
     */
    public function test_a_plugin_with_no_events_returns_an_empty_list(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->ownedBy($owner);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$plugin->id}/events")
            ->assertOk()
            ->assertJsonCount(0, 'data.events')
            ->assertJsonPath('meta.total', 0);
    }

    /**
     * The owner of one plugin must never see another plugin's history through
     * the same endpoint.
     */
    public function test_a_timeline_never_leaks_across_plugins(): void
    {
        $owner = User::factory()->create();
        $other = $this->ownedBy(User::factory()->create());
        $mine = $this->ownedBy($owner);

        PluginEvent::factory()->created()->create(['plugin_id' => $other->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/plugins/{$mine->id}/events")
            ->assertOk()
            ->assertJsonCount(0, 'data.events');
    }
}
