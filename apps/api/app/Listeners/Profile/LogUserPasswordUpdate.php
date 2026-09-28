<?php
namespace App\Listeners\Profile;

use App\Events\Profile\UserPasswordUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogUserPasswordUpdate implements ShouldQueue
{
    public function handle(UserPasswordUpdated $event): void
    {
        Log::info('User manually updated their password in settings', ['user_id' => $event->user->id]);
    }
}
