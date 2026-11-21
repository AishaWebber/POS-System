<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SalesReceiptMail extends Mailable
{
    public function __construct(public Sale $sale) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->receiptSubject());
    }

    public function receiptSubject(): string
    {
        return 'POS-System receipt #'.str_pad((string) $this->sale->id, 5, '0', STR_PAD_LEFT);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sales-receipt', text: 'emails.sales-receipt-text');
    }
}
