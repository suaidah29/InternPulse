<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One reminder, delivered by email. The commands supply the bilingual copy so
 * the wording stays next to the rule that triggered it.
 */
class ReminderMail extends Mailable
{
    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public array $lines = [],
        public string $actionLabel = 'Open InternPulse',
        public string $actionUrl = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder');
    }
}
