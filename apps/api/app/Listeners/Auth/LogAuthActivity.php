<?php

namespace App\Listeners\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Listeners\Concerns\ResolvesAuditLogChannel;
use App\Listeners\Concerns\ResolvesRetryPolicy;
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
    use ResolvesAuditLogChannel;
    use ResolvesRetryPolicy;

    public function handle(RecordsAuthActivity $event): void
    {
        Log::channel($this->auditChannel())
            ->info($event->eventType()->label(), $event->context());
    }

    /**
     * Called once the job has exhausted every attempt.
     *
     * Reported on a channel of its own: the channel the audit entry went to is
     * the one that just failed, so writing there again raises the very exception
     * being reported and the line never lands anywhere. See
     * ResolvesAuditLogChannel.
     */
    public function failed(RecordsAuthActivity $event, Throwable $e): void
    {
        try {
            Log::channel($this->failureChannel())->error('Auth audit logging failed.', [
                'event_type' => $event->eventType()->value,
                'user_id' => $event->user()->getKey(),
                'ip_address' => $event->ipAddress(),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // Last resort: never let the failure reporter itself throw.
            error_log('Auth audit logging failed: '.$e->getMessage());
        }
    }

    public function viaQueue(): string
    {
        return (string) config('queue.auth_queue', 'default');
    }

    protected function auditChannelConfigKey(): string
    {
        return 'logging.auth_channel';
    }

    protected function failureChannelConfigKey(): string
    {
        return 'logging.auth_failure_channel';
    }
}
