<?php

namespace App\Listeners\Plugin;

use App\Events\Plugin\PluginSubmitted;
use App\Listeners\Concerns\ResolvesRetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LogPluginSubmission implements ShouldQueue
{
    use ResolvesRetryPolicy;

    public function handle(PluginSubmitted $event): void
    {
        Log::channel(config('logging.plugin_channel', config('logging.default')))
            ->info('Plugin submitted.', $event->context());
    }

    /**
     * Called once the job has exhausted every attempt.
     */
    public function failed(PluginSubmitted $event, Throwable $e): void
    {
        Log::channel(config('logging.default'))->error('Plugin audit logging failed.', [
            'plugin_id' => $event->plugin->getKey(),
            'user_id' => $event->user->getKey(),
            'ip_address' => $event->ipAddress,
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }

    public function viaQueue(): string
    {
        return (string) config('queue.plugin_queue', 'default');
    }
}
