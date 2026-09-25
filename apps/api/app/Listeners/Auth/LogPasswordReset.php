<?php
namespace App\Listeners\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogPasswordReset implements ShouldQueue
{
    public function handle(PasswordReset $event): void
    {
        Log::info('User successfully reset their password via token', ['user_id' => $event->user->id]);
    }
}
