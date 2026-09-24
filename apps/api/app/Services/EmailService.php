<?php

namespace App\Services;

use App\Models\User;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send a welcome email to the newly registered user.
     */
    public function sendWelcomeEmail(User $user): void
    {
        Mail::to($user->email)->send(new WelcomeMail($user));
    }
}
