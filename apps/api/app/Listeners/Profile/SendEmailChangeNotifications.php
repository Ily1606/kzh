<?php

namespace App\Listeners\Profile;

use App\Events\Profile\EmailChangeRequested;
use App\Mail\EmailChangeAlertMail;
use App\Mail\EmailChangeVerifyMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendEmailChangeNotifications implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EmailChangeRequested $event): void
    {
        Mail::to($event->newEmail)->send(new EmailChangeVerifyMail($event->token));
        Mail::to($event->user->email)->send(new EmailChangeAlertMail($event->newEmail));
    }
}
