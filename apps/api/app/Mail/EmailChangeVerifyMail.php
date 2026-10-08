<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.email_change_verify.subject'),
        );
    }

    public function content(): Content
    {
        $url = config('app.frontend_url') . '/profile?token=' . $this->token;

        return new Content(
            markdown: 'emails.profile.email_change_verify',
            with: [
                'url' => $url,
            ]
        );
    }
}
