<?php

namespace Tests\Unit\Services;

use App\Contracts\PluginRepositoryInterface;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginRepositoryMock = Mockery::mock(PluginRepositoryInterface::class);
        $this->pluginService = new PluginService($this->pluginRepositoryMock);
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
}
