<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountVerificationMail extends Mailable
{
    public function __construct(public string $staffName, public string $verificationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your POS-System account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-verification', text: 'emails.account-verification-text');
    }
}
