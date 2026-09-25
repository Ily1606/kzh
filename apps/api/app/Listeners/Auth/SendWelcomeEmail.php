<?php

namespace App\Listeners\Auth;

use App\Events\Auth\UserRegistered;
use App\Mail\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user()->email)->send(new WelcomeMail($event->user()));
    }
}
