<?php

namespace App\Listeners\Star;

use App\Events\Star\PluginStarred;
use App\Events\Star\PluginUnstarred;
use App\Listeners\Concerns\ResolvesAuditLogChannel;
use App\Listeners\Concerns\ResolvesRetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audit entry for both star events.
 *
 * One listener for both events, subscribing to the union type: the write, the
 * retry policy and the failure report are identical for starring and unstarring,
 * so duplicating them would give the two paths a chance to drift apart. Only the
 * log message differs, and that is one method below.
 */
final class LogStarChange implements ShouldQueue
{
    use ResolvesAuditLogChannel;
    use ResolvesRetryPolicy;

    public function handle(PluginStarred|PluginUnstarred $event): void
    {
        Log::channel($this->auditChannel())
            ->info($this->messageFor($event), $event->context());
    }

    /**
     * Wording per event type.
     *
     * Driven off the class rather than a property on the event so the two events
     * carry no audit-specific state — they describe the domain, not the log.
     */
    private function messageFor(PluginStarred|PluginUnstarred $event): string
    {
        return $event instanceof PluginStarred
            ? 'Plugin starred.'
            : 'Plugin unstarred.';
    }

    /**
     * Called once the job has exhausted every attempt.
     *
     * Reported on a channel of its own, for the same reason as the auth, plugin
     * and comment listeners: the audit channel is the one that just failed. See
     * ResolvesAuditLogChannel.
     */
    public function failed(PluginStarred|PluginUnstarred $event, Throwable $e): void
    {
        try {
            Log::channel($this->failureChannel())->error('Star audit logging failed.', [
                'plugin_id' => $event->snapshot['plugin_id'],
                'user_id' => $event->snapshot['user_id'],
                'ip_address' => $event->requestContext->ipAddress,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // Last resort: never let the failure reporter itself throw.
            error_log('Star audit logging failed: '.$e->getMessage());
        }
    }

    public function viaQueue(): string
    {
        return (string) config('queue.star_queue', 'default');
    }

    protected function auditChannelConfigKey(): string
    {
        return 'logging.star_channel';
    }

    protected function failureChannelConfigKey(): string
    {
        return 'logging.star_failure_channel';
    }
}
