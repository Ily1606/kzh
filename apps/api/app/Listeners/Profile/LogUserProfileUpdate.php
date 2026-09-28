<?php
namespace App\Listeners\Profile;

use App\Events\Profile\UserProfileUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogUserProfileUpdate implements ShouldQueue
{
    public function handle(UserProfileUpdated $event): void
    {
        Log::info('User updated their profile', ['user_id' => $event->user->id]);
    }
}
