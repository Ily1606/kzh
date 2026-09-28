<?php
namespace App\Listeners\Auth;

use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogPasswordResetLinkSent implements ShouldQueue
{
    public function handle(PasswordResetLinkSent $event): void
    {
        Log::info('Password reset link sent to user', ['email' => $event->user->email]);
    }
}
