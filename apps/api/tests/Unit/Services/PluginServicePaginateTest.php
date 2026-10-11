<?php

namespace Tests\Unit\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginEventService;
use App\Services\PluginService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PluginServicePaginateTest extends TestCase
{
    private PluginService $pluginService;

    private MockInterface|PluginRepositoryInterface $pluginRepositoryMock;

    private MockInterface|StarRepositoryInterface $starRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginRepositoryMock = Mockery::mock(PluginRepositoryInterface::class);
        $this->starRepositoryMock = Mockery::mock(StarRepositoryInterface::class);

        // The timeline service is a collaborator of PluginService but plays no
        // part in pagination, which is all this file exercises. Resolved from
        // the container rather than mocked because it is final, and the
        // constructor binds its repository lazily — nothing here calls it.
        $this->pluginService = new PluginService(
            $this->pluginRepositoryMock,
            $this->starRepositoryMock,
            app(PluginEventService::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pag_01_02_03_returns_paginator_for_different_per_page_values(): void
    {
        $perPages = [10, 1, 100];

        foreach ($perPages as $perPage) {
            $expectedPaginator = new LengthAwarePaginator([], 0, $perPage);

            $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
                ->with($perPage)
                ->once()
                ->andReturn($expectedPaginator);

            $result = $this->pluginService->getPaginatedApprovedPlugins($perPage);

            $this->assertSame($expectedPaginator, $result);
        }
    }

    public function test_pag_04_returns_empty_paginator_when_no_plugins(): void
    {
        $emptyPaginator = new LengthAwarePaginator([], 0, 10);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with(10)
            ->once()
            ->andReturn($emptyPaginator);

        $result = $this->pluginService->getPaginatedApprovedPlugins(10);

        $this->assertSame($emptyPaginator, $result);
        $this->assertEmpty($result->items());
    }

    public function test_pag_05_returns_paginator_with_many_plugins(): void
    {
        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $paginator = new LengthAwarePaginator($items, 100, 10);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with(10)
            ->once()
            ->andReturn($paginator);

        $result = $this->pluginService->getPaginatedApprovedPlugins(10);

        $this->assertSame($paginator, $result);
        $this->assertCount(3, $result->items());
    }

    public function test_pag_06_passes_exact_per_page_parameter_to_repository(): void
    {
        $expectedPerPage = 20;

        // Verify the repository is called EXACTLY once with 20
        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with($expectedPerPage)
            ->once()
            ->andReturn(new LengthAwarePaginator([], 0, $expectedPerPage));

        $this->pluginService->getPaginatedApprovedPlugins($expectedPerPage);

        // Mockery verifies expectations on tearDown (or when mock is closed),
        // but we add a dummy assertion to ensure test count isn't 0
        $this->assertTrue(true);
    }

    public function test_pag_07_propagates_repository_exception(): void
    {
        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with(10)
            ->once()
            ->andThrow(new Exception('Database connection failed'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->pluginService->getPaginatedApprovedPlugins(10);
    }

    public function test_pag_08_preserves_pagination_metadata(): void
    {
        $items = [['id' => 1]];
        $total = 50;
        $perPage = 10;
        $currentPage = 3;

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $currentPage, [
            'path' => '/api/v1/plugins',
            'pageName' => 'page',
        ]);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with($perPage)
            ->once()
            ->andReturn($paginator);

        $result = $this->pluginService->getPaginatedApprovedPlugins($perPage);

        $this->assertSame($paginator, $result);
        $this->assertEquals(50, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(3, $result->currentPage());
        $this->assertEquals('/api/v1/plugins', $result->path());
    }

    // -----------------------------------------------------------------------
    // is_star flag
    // -----------------------------------------------------------------------

    public function test_marks_is_star_on_each_plugin_for_a_signed_in_user(): void
    {
        // Mocked, not persisted: this suite runs without migrations, and the
        // service only reads the auth identifier off the user.
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn('user-uuid');

        $starred = (new Plugin)->forceFill(['id' => '11111111-1111-1111-1111-111111111111']);
        $other = (new Plugin)->forceFill(['id' => '22222222-2222-2222-2222-222222222222']);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with(10)
            ->once()
            ->andReturn(new LengthAwarePaginator([$starred, $other], 2, 10));

        $this->starRepositoryMock->shouldReceive('starredPluginIds')
            ->with([$starred->id, $other->id], 'user-uuid')
            ->once()
            ->andReturn([$starred->id]);

        $result = $this->pluginService->getPaginatedApprovedPlugins(10, $user);

        $this->assertTrue($result->items()[0]->is_star);
        $this->assertFalse($result->items()[1]->is_star);
    }

    public function test_does_not_resolve_stars_for_a_guest(): void
    {
        $plugin = (new Plugin)->forceFill(['id' => '11111111-1111-1111-1111-111111111111']);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedApprovedPlugins')
            ->with(10)
            ->once()
            ->andReturn(new LengthAwarePaginator([$plugin], 1, 10));

        $this->starRepositoryMock->shouldNotReceive('starredPluginIds');

        $result = $this->pluginService->getPaginatedApprovedPlugins(10);

        $this->assertNull($result->items()[0]->is_star);
    }

    // -----------------------------------------------------------------------
    // is_star on the owner's own list
    // -----------------------------------------------------------------------

    public function test_marks_is_star_on_approved_plugins_only_for_my_plugins(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn('user-uuid');

        $approved = (new Plugin)->forceFill([
            'id' => '11111111-1111-1111-1111-111111111111',
            'status' => PluginStatus::Approved,
        ]);
        $pending = (new Plugin)->forceFill([
            'id' => '22222222-2222-2222-2222-222222222222',
            'status' => PluginStatus::Pending,
        ]);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedPluginsByUser')
            ->with('user-uuid', null, 10)
            ->once()
            ->andReturn(new LengthAwarePaginator([$approved, $pending], 2, 10));

        // Only the approved id is sent: the pending one cannot carry a star, so
        // asking about it would be a question with a foregone answer.
        $this->starRepositoryMock->shouldReceive('starredPluginIds')
            ->with([$approved->id], 'user-uuid')
            ->once()
            ->andReturn([$approved->id]);

        $result = $this->pluginService->getPaginatedPluginsByUser($user, null, 10);

        $this->assertTrue($result->items()[0]->is_star);
        $this->assertNull($result->items()[1]->is_star);
    }

    public function test_does_not_query_stars_when_the_page_holds_no_approved_plugin(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn('user-uuid');

        $pending = (new Plugin)->forceFill([
            'id' => '11111111-1111-1111-1111-111111111111',
            'status' => PluginStatus::Pending,
        ]);

        $this->pluginRepositoryMock->shouldReceive('getPaginatedPluginsByUser')
            ->with('user-uuid', PluginStatus::Pending, 10)
            ->once()
            ->andReturn(new LengthAwarePaginator([$pending], 1, 10));

        $this->starRepositoryMock->shouldNotReceive('starredPluginIds');

        $result = $this->pluginService->getPaginatedPluginsByUser($user, PluginStatus::Pending, 10);

        $this->assertNull($result->items()[0]->is_star);
    }
}
