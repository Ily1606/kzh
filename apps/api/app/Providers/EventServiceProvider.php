<?php

namespace App\Providers;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\LogAuthActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Auth events implement App\Contracts\RecordsAuthActivity, so a single
     * listener can consume all of them. Add more listeners here (audit trail,
     * notifications, analytics...) without touching the auth service.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserRegistered::class => [
            LogAuthActivity::class,
        ],
        UserLoggedIn::class => [
            LogAuthActivity::class,
        ],
        UserLoggedOut::class => [
            LogAuthActivity::class,
        ],
    ];
}
