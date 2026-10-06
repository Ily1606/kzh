<?php

namespace Tests\Unit\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `star_count` is no longer a column — it is COUNT over `stars`. These tests
 * pin the property the reviewer asked for: rows removed outside the star
 * endpoint (a banned user, a cascade delete, a manual DB fix) are reflected
 * immediately, with no counter to drift.
 */
class PluginStarCountTest extends TestCase
{
    use RefreshDatabase;

    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    /**
     * Star rows for distinct users — the composite primary key forbids the
     * same user starring twice.
     */
    private function giveStars(Plugin $plugin, int $count): void
    {
        User::factory()->count($count)->create()->each(function (User $stargazer) use ($plugin): void {
            DB::table('stars')->insert([
                'plugin_id' => $plugin->id,
                'user_id' => $stargazer->id,
            ]);
        });
    }

    public function test_the_count_is_the_number_of_rows_in_the_stars_table(): void
    {
        $this->giveStars($this->plugin, 3);

        $this->assertSame(3, $this->plugin->stars()->count());
        $this->assertSame(3, $this->listCount());
    }

    public function test_rows_removed_outside_the_star_endpoint_shrink_the_count(): void
    {
        $this->giveStars($this->plugin, 3);

        // Simulate a moderation/cascade write that never goes through
        // StarService: the count must follow the rows, not a counter.
        DB::table('stars')->where('plugin_id', $this->plugin->id)->limit(2)->delete();

        $this->assertSame(1, $this->plugin->stars()->count());
        $this->assertSame(1, $this->listCount());
    }

    public function test_the_count_never_goes_negative_because_it_is_a_count(): void
    {
        $this->giveStars($this->plugin, 1);

        DB::table('stars')->where('plugin_id', $this->plugin->id)->delete();
        DB::table('stars')->where('plugin_id', $this->plugin->id)->delete();

        $this->assertSame(0, $this->plugin->stars()->count());
        $this->assertSame(0, $this->listCount());
    }

    /**
     * The list endpoint is where a stale counter would surface to users:
     * it must report the same number as a direct COUNT.
     */
    private function listCount(): int
    {
        return (int) app(PluginRepositoryInterface::class)
            ->getPaginatedApprovedPlugins(15)
            ->first()
            ->star_count;
    }
}
