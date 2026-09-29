<?php

namespace App\Listeners\Concerns;

/**
 * Gives a queued listener its retry policy from configuration.
 *
 * Laravel reads a listener's `tries`/`backoff` from the `tries()`/`backoff()`
 * methods when they exist, and only falls back to a same-named property
 * otherwise (see Illuminate\Events\Dispatcher::propagateListenerOptions).
 * Methods are what make the values configurable: PHP forbids calling config()
 * from a property initializer, and a constructor is no help either because the
 * dispatcher builds listeners with newInstanceWithoutConstructor().
 *
 * Every listener using this trait shares one policy, so operations can retune
 * the retry behaviour per environment without a code change.
 */
trait ResolvesRetryPolicy
{
    /**
     * Number of attempts before the job is marked as failed.
     */
    public function tries(): int
    {
        return (int) config('queue.audit_retry.tries', 3);
    }

    /**
     * Seconds to wait before each retry.
     *
     * Accepts either an array ([10, 60]) or a comma separated string ('10,60').
     * Laravel applies the nth value to the nth attempt and reuses the last one
     * for any further attempt, so a list shorter than `tries` is fine.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $backoff = config('queue.audit_retry.backoff', [10, 60]);

        if (is_string($backoff)) {
            $backoff = explode(',', $backoff);
        }

        $delays = array_filter(
            array_map('trim', (array) $backoff),
            static fn (string $delay): bool => $delay !== '',
        );

        return array_values(array_map('intval', $delays));
    }
}
