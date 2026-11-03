<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupervisorInvitationMail extends Mailable
{
    public function __construct(public string $inviteeName, public string $department, public string $invitationUrl, public string $inviteRole = 'supervisor') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your InternPulse invitation / Jemputan InternPulse');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.supervisor-invitation');
    }
}
