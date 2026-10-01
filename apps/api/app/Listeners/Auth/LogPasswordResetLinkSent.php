<?php

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPasswordResetLinkSent implements ShouldQueue
{
    public function handle(PasswordResetLinkSent $event): void
    {
        Log::info('Password reset link sent to user', [
            'email' => Str::mask($event->user->email, '*', 3),
        ]);
    }
}
