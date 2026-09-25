<?php

namespace App\Listeners\Auth;

use App\Contracts\RecordsAuthActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Default listener for every auth lifecycle event.
 *
 * Any event implementing {@see RecordsAuthActivity} is logged with the same
 * structure, so adding a new auth event automatically gets auditing for free.
 */
final class LogAuthActivity implements ShouldQueue
{
    public function handle(RecordsAuthActivity $event): void
    {
        Log::channel(config('logging.auth_channel', config('logging.default')))
            ->info($event->eventType()->label(), $event->context());
    }

    public function viaQueue(): string
    {
        return (string) config('queue.auth_queue', 'default');
    }
}
