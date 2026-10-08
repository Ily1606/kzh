<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $newEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security Alert: Email Change Requested',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.profile.email_change_alert',
        );
    }
}
