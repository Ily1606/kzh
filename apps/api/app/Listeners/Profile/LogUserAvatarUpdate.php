<?php
namespace App\Listeners\Profile;

use App\Events\Profile\UserAvatarUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogUserAvatarUpdate implements ShouldQueue
{
    public function handle(UserAvatarUpdated $event): void
    {
        Log::info('User updated their avatar', ['user_id' => $event->user->id]);
    }
}
