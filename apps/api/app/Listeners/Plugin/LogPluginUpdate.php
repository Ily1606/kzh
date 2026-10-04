<?php

namespace App\Listeners\Plugin;

use App\Events\Plugin\PluginUpdated;
use App\Listeners\Concerns\ResolvesAuditLogChannel;
use App\Listeners\Concerns\ResolvesRetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audit entry for a plugin edit.
 *
 * Shares the plugin channel and queue with LogPluginSubmission: both record
 * what a submitter did to a plugin, and splitting them would scatter one
 * feature's history across two log streams.
 */
final class LogPluginUpdate implements ShouldQueue
{
    use ResolvesAuditLogChannel;
    use ResolvesRetryPolicy;

    public function handle(PluginUpdated $event): void
    {
        Log::channel($this->auditChannel())
            ->info('Plugin updated.', $event->context());
    }

    /**
     * Called once the job has exhausted every attempt.
     *
     * Reported on a channel of its own, for the same reason as the plugin
     * submission listener: the audit channel is the one that just failed. See
     * ResolvesAuditLogChannel.
     */
    public function failed(PluginUpdated $event, Throwable $e): void
    {
        try {
            Log::channel($this->failureChannel())->error('Plugin update audit logging failed.', [
                'plugin_id' => $event->snapshot['plugin_id'],
                'user_id' => $event->snapshot['user_id'],
                'ip_address' => $event->requestContext->ipAddress,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // Last resort: never let the failure reporter itself throw.
            error_log('Plugin update audit logging failed: '.$e->getMessage());
        }
    }

    public function viaQueue(): string
    {
        return (string) config('queue.plugin_queue', 'default');
    }

    protected function auditChannelConfigKey(): string
    {
        return 'logging.plugin_channel';
    }

    protected function failureChannelConfigKey(): string
    {
        return 'logging.plugin_failure_channel';
    }
}
