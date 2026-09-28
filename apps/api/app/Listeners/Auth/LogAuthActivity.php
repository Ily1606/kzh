<?php

namespace App\Listeners\Auth;

use App\Contracts\RecordsAuthActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Default listener for every auth lifecycle event.
 *
 * Any event implementing {@see RecordsAuthActivity} is logged with the same
 * structure, so adding a new auth event automatically gets auditing for free.
 */
final class LogAuthActivity implements ShouldQueue
{

    /**
     * Number of retries
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function handle(RecordsAuthActivity $event): void
    {
        Log::channel(config('logging.auth_channel', config('logging.default')))
            ->info($event->eventType()->label(), $event->context());
    }

    /**
     * Called once the job has exhausted every attempt.
     *
     * Logged to the default channel on purpose: the auth channel is the one that
     * just failed, so writing there again would be lost.
     */
    public function failed(RecordsAuthActivity $event, Throwable $e): void
    {
        $context = $event->context();

        Log::channel(config('logging.default'))->error('Auth audit logging failed.', [
            'event_type' => $event->eventType()->value,
            'user_id' => $event->user()->getKey(),
            'ip_address' => $context['ip_address'] ?? null,
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }

    public function viaQueue(): string
    {
        return (string) config('queue.auth_queue', 'default');
    }
}
