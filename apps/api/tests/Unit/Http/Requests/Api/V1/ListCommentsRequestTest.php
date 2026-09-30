<?php

namespace Tests\Unit\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\ListCommentsRequest;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises `ListCommentsRequest` directly, following the shape of
 * SubmitPluginRequestTest.
 *
 * The clamping rules cannot be observed from a feature test: the HTTP layer caps
 * nothing itself, so `per_page=9999` reaching `perPage()` intact is precisely
 * what this class is responsible for handling. Building the request here keeps
 * these tests pinned to the class's own logic rather than to middleware.
 */
class ListCommentsRequestTest extends TestCase
{
    private const PLUGIN_ID = '0195f0a0-0000-7000-8000-000000000000';

    /**
     * Builds the request without the HTTP kernel and runs the rules.
     *
     * @param  array<string, mixed>  $query
     */
    private function resolved(array $query): ListCommentsRequest
    {
        $request = ListCommentsRequest::create(
            '/api/v1/plugins/'.self::PLUGIN_ID.'/comments',
            'GET',
            $query,
        );

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $request->validateResolved();

        return $request;
    }

    // -----------------------------------------------------------------------
    // perPage()
    // -----------------------------------------------------------------------

    public function test_it_falls_back_to_the_configured_default_page_size(): void
    {
        config()->set('comments.pagination.default_per_page', 20);
        config()->set('comments.pagination.max_per_page', 50);

        $this->assertSame(20, $this->resolved([])->perPage());
    }

    /**
     * Only the upper bound is reachable through validation: the `min:1` rule
     * rejects 0 and negatives before `perPage()` ever sees them, so the lower
     * clamp in `perPage()` is defence in depth for a config whose
     * `default_per_page` is misconfigured to 0 or lower.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function clampedPerPageProvider(): array
    {
        return [
            'one' => [1, 1],
            'within bounds' => [10, 10],
            'at the ceiling' => [50, 50],
            'above the ceiling' => [1000, 50],
        ];
    }

    /**
     * A greedy client degrades to the cap instead of getting a 422.
     */
    #[DataProvider('clampedPerPageProvider')]
    public function test_per_page_is_clamped_to_the_configured_maximum(int $requested, int $expected): void
    {
        config()->set('comments.pagination.default_per_page', 20);
        config()->set('comments.pagination.max_per_page', 50);

        $this->assertSame($expected, $this->resolved(['per_page' => $requested])->perPage());
    }

    /**
     * The lower clamp is unreachable via the query string, so it is pinned
     * against the config default: a misconfigured `default_per_page` of 0 must
     * not hand a zero page size to the paginator.
     */
    public function test_a_non_positive_default_page_size_is_floored_at_one(): void
    {
        config()->set('comments.pagination.default_per_page', 0);
        config()->set('comments.pagination.max_per_page', 50);

        $this->assertSame(1, $this->resolved([])->perPage());
    }

    // -----------------------------------------------------------------------
    // sort()
    // -----------------------------------------------------------------------

    public function test_it_falls_back_to_the_configured_default_sort(): void
    {
        config()->set('comments.default_sort', 'newest');
        config()->set('comments.sorts', ['newest', 'oldest']);

        $this->assertSame('newest', $this->resolved([])->sort());
    }

    public function test_it_returns_the_requested_sort_when_it_is_supported(): void
    {
        config()->set('comments.sorts', ['newest', 'oldest']);

        $this->assertSame('oldest', $this->resolved(['sort' => 'oldest'])->sort());
    }

    /**
     * `sort()` re-checks the allow-list instead of trusting `validated()`. That
     * guard is unreachable through the query string — an unknown `sort` is
     * rejected by `Rule::in` before the controller runs — but it is reachable
     * through config: if `comments.sorts` no longer contains
     * `comments.default_sort`, the fallback must still hand the repository a
     * key it knows, so `sort()` pins to the hardcoded `'newest'`.
     */
    public function test_it_falls_back_to_newest_when_the_configured_default_is_not_allowed(): void
    {
        config()->set('comments.default_sort', 'oldest');
        config()->set('comments.sorts', ['newest']);

        $this->assertSame('newest', $this->resolved([])->sort());
    }

    public function test_it_returns_the_configured_default_when_it_is_allowed(): void
    {
        config()->set('comments.default_sort', 'oldest');
        config()->set('comments.sorts', ['newest', 'oldest']);

        $this->assertSame('oldest', $this->resolved([])->sort());
    }

    // -----------------------------------------------------------------------
    // rules()
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function rejectedQueryProvider(): array
    {
        return [
            'zero' => [['per_page' => 0], 'per_page'],
            'negative' => [['per_page' => -1], 'per_page'],
            'non numeric' => [['per_page' => 'abc'], 'per_page'],
            'unknown sort' => [['sort' => 'top'], 'sort'],
            'non string sort' => [['sort' => ['newest']], 'sort'],
        ];
    }

    #[DataProvider('rejectedQueryProvider')]
    public function test_invalid_query_parameters_fail_validation(array $query, string $field): void
    {
        try {
            $this->resolved($query);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail('Expected a ValidationException for: '.json_encode($query));
    }

    public function test_an_empty_query_is_valid(): void
    {
        $request = $this->resolved([]);

        $this->assertIsInt($request->perPage());
        $this->assertIsString($request->sort());
    }
}
