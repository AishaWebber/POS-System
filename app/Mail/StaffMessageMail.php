<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffMessageMail extends Mailable
{
    public function __construct(public string $messageSubject, public string $messageBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message', text: 'emails.message-text');
    }
}
