<?php

namespace App\Providers;

use App\Contracts\RecordsAuthActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Wildcard matching every event declared under App\Events.
     */
    private const EVENT_WILDCARD = 'App\\Events\\*';

    /**
     * Additional listeners for auth lifecycle events, in execution order.
     *
     * These are listeners the framework does not discover on its own — an audit
     * trail, notifications, analytics. Auth events implement {@see RecordsAuthActivity},
     * so one registration below reaches all of them and adding an event needs no
     * change here.
     *
     * App\Listeners\Auth\LogAuthActivity is deliberately absent: it type-hints
     * RecordsAuthActivity and is therefore already attached to every auth event
     * by Laravel's event discovery. Listing it here would log each event twice.
     *
     * @var array<int, class-string>
     */
    private const AUTH_LISTENERS = [
        // \App\Listeners\Auth\NotifySecurityTeam::class,
    ];

    /**
     * Subscribe once to every event in App\Events instead of listing events.
     *
     * Laravel matches wildcards with Str::is(), so this single subscription
     * covers present and future events alike: a newly added auth event reaches
     * the listeners above automatically rather than needing another $listen entry.
     */
    public function boot(): void
    {
        Event::listen(self::EVENT_WILDCARD, function (string $eventName, array $payload): void {
            $this->dispatchToAuthListeners($payload[0] ?? null);
        });
    }

    /**
     * Route the event to every listener that supports it.
     *
     * Wildcard callbacks receive the raw event name and payload array rather
     * than the event instance, so the support check lives here instead of being
     * type-hinted on the listener. Events carrying some other payload (or none)
     * are skipped rather than raising a TypeError.
     */
    private function dispatchToAuthListeners(mixed $event): void
    {
        if (! $event instanceof RecordsAuthActivity) {
            return;
        }

        foreach (self::AUTH_LISTENERS as $listener) {
            $this->app->call([$this->app->make($listener), 'handle'], ['event' => $event]);
        }
    }
}
