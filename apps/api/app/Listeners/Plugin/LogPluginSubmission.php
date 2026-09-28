<?php

namespace App\Listeners\Plugin;

use App\Events\Plugin\PluginSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogPluginSubmission implements ShouldQueue
{
    public function handle(PluginSubmitted $event): void
    {
        Log::channel(config('logging.plugin_channel', config('logging.default')))
            ->info('Plugin submitted.', $event->context());
    }

    public function viaQueue(): string
    {
        return (string) config('queue.plugin_queue', 'default');
    }
}
