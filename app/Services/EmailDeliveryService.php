<?php

namespace App\Services;

use App\Exceptions\EmailDeliveryException;
use App\Mail\AccountVerificationMail;
use App\Mail\SalesReceiptMail;
use App\Mail\StaffMessageMail;
use App\Models\OutgoingEmail;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EmailDeliveryService
{
    public function __construct(private PosMailService $mail) {}

    public function sendVerification(User $user): OutgoingEmail
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);

        return $this->deliver($user->email, 'Confirm your POS-System account',
            new AccountVerificationMail($user->full_name, $url), 'verification', null,
            $user->id, bin2hex(random_bytes(32)), hash('sha256', $url));
    }

    public function sendMessage(string $recipient, string $subject, string $body, int $staffId, string $key): OutgoingEmail
    {
        return $this->deliver($recipient, $subject, new StaffMessageMail($subject, $body), 'message', null, $staffId, $key, hash('sha256', json_encode([$recipient, $subject, $body])));
    }

    public function sendReceipt(Sale $sale, string $recipient, int $staffId, string $key): OutgoingEmail
    {
        $message = new SalesReceiptMail($sale);
        $hash = hash('sha256', json_encode([$recipient, $sale->id, $sale->product_name, $sale->quantity, $sale->total_price, $sale->customer_name, $sale->staff_name, $sale->created_at->toISOString()]));

        return $this->deliver($recipient, $message->receiptSubject(), $message, 'receipt', $sale->id, $staffId, $key, $hash);
    }

    private function deliver(string $recipient, string $subject, Mailable $message, string $category, ?int $saleId, int $staffId, string $key, string $hash): OutgoingEmail
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new InvalidArgumentException('Reload the email form and submit again.');
        }
        $settings = $this->mail->settings();
        if (! $this->mail->ready($settings)) {
            throw new InvalidArgumentException('Configure SMTP in Email settings before sending an email.');
        }
        $entry = OutgoingEmail::firstOrCreate(['request_key' => $key], [
            'payload_hash' => $hash, 'category' => $category, 'sale_id' => $saleId, 'sent_by' => $staffId,
            'recipient' => $recipient, 'subject' => $subject, 'from_address' => $settings->from_address, 'status' => 'sending',
        ]);
        if (! $entry->wasRecentlyCreated) {
            if ((int) $entry->sent_by !== $staffId || ! hash_equals($entry->payload_hash, $hash)) {
                throw new InvalidArgumentException('This email form has already been submitted with different details. Open a new form.');
            }
            if ($entry->status === 'accepted') {
                return $entry;
            }
            throw new EmailDeliveryException('This email has already been attempted or is being processed. Check Email history and your provider delivery logs before retrying.');
        }
        try {
            $sent = $this->mail->mailer($settings)->to($recipient)->send($message);
            if (! $sent) {
                throw new \RuntimeException('SMTP transmission was cancelled.');
            }
        } catch (\Throwable $e) {
            $reference = (string) Str::uuid();
            try {
                $entry->update(['status' => 'unconfirmed', 'error_reference' => $reference]);
            } catch (\Throwable) {
            }
            Log::warning('Email sending could not be confirmed.', ['reference' => $reference, 'email_id' => $entry->id, 'exception_type' => get_class($e)]);
            throw new EmailDeliveryException('Email sending could not be confirmed. Check SMTP settings and provider delivery logs before retrying. Reference: '.$reference);
        }
        try {
            $entry->update(['status' => 'accepted', 'message_id' => substr((string) $sent->getMessageId(), 0, 255), 'accepted_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('SMTP accepted an email but history could not be updated.', ['email_id' => $entry->id, 'exception_type' => get_class($e)]);
            throw new EmailDeliveryException('The SMTP server accepted the email, but its history could not be updated. Check provider logs before sending again.');
        }

        return $entry;
    }
}
