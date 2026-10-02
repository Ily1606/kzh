<?php

namespace Tests\Unit\Repositories;

use App\Models\Plugin;
use App\Repositories\PluginRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginStarCountTest extends TestCase
{
    use RefreshDatabase;

    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = Plugin::factory()->create(['star_count' => 0]);
    }

    private function starCount(): int
    {
        return (int) $this->plugin->fresh()->star_count;
    }

    /**
     * `star_count` is not in the Plugin fillable list, so mass assignment is
     * silently dropped. Seed it through the factory instead — the same way the
     * comment tests seed `comment_count`.
     */
    private function pluginWithStars(int $starCount): Plugin
    {
        $this->plugin = Plugin::factory()->create(['star_count' => $starCount]);

        return $this->plugin;
    }

    /**
     * Uses an amount above 1 on purpose: the method has to apply whatever value
     * it is given rather than hard-coding a step of one.
     */
    public function test_a_positive_amount_increases_the_counter(): void
    {
        app(PluginRepository::class)->changeStarCount($this->plugin->id, 3);

        $this->assertSame(3, $this->starCount());
    }

    public function test_a_negative_amount_decreases_the_counter(): void
    {
        $this->pluginWithStars(3);

        app(PluginRepository::class)->changeStarCount($this->plugin->id, -1);

        $this->assertSame(2, $this->starCount());
    }

    public function test_a_zero_amount_changes_nothing(): void
    {
        $this->pluginWithStars(4);

        app(PluginRepository::class)->changeStarCount($this->plugin->id, 0);

        $this->assertSame(4, $this->starCount());
    }

    /**
     * `star_count` is `unsignedInteger`, but only MySQL enforces that. PostgreSQL
     * stores a plain integer and happily stores -1, so the `where star_count >=`
     * guard in the method — not the column — is what keeps this at zero.
     */
    public function test_a_negative_amount_never_goes_below_zero(): void
    {
        app(PluginRepository::class)->changeStarCount($this->plugin->id, -1);
        app(PluginRepository::class)->changeStarCount($this->plugin->id, -1);
        app(PluginRepository::class)->changeStarCount($this->plugin->id, -1);

        $this->assertSame(0, $this->starCount());
    }

    /**
     * The decrement is all-or-nothing: it applies only when the counter can
     * absorb the whole amount. Asking for -5 against a counter of 2 leaves it at
     * 2 — never -3.
     *
     * A clamp-to-zero would need `GREATEST()` in the statement, and that is not
     * worth it here: StarService only ever passes ±1, so the case cannot arise in
     * practice. What matters, and what the `where` clause buys, is that the
     * counter is never driven negative on PostgreSQL.
     */
    public function test_a_negative_amount_larger_than_the_counter_is_not_applied(): void
    {
        $this->pluginWithStars(2);

        app(PluginRepository::class)->changeStarCount($this->plugin->id, -5);

        $this->assertSame(2, $this->starCount());
    }

    /**
     * Starring a plugin is not an edit of that plugin. The counter is bumped with
     * the query builder precisely so `updated_at` is left alone — Eloquent's
     * `increment()` would call `addUpdatedAtColumn()` and rewrite it.
     */
    public function test_changing_the_count_does_not_touch_the_plugin_updated_at(): void
    {
        $before = $this->plugin->updated_at;

        app(PluginRepository::class)->changeStarCount($this->plugin->id, 1);

        $this->assertTrue($before->equalTo($this->plugin->fresh()->updated_at));
    }
}
