<?php

namespace Tests\Concerns;

use App\Models\MailSetting;
use App\Services\PosMailService;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;

trait MocksRegistrationSmtp
{
    protected array $verificationMessages = [];

    protected function configureRegistrationSmtp(): void
    {
        MailSetting::forceCreate(['id' => 1, 'mailer' => 'smtp', 'from_name' => 'POS-System', 'from_address' => 'sender@example.com', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'encryption' => 'tls']);
    }

    protected function captureRegistrationSmtp(bool $fail = false, int $count = 1): void
    {
        $email = (new Email)->from('sender@example.com')->to('member@example.com')->subject('Confirm your POS-System account')->text('Confirm your email.');
        $sent = new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));
        $pending = \Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->times($count)->andReturnUsing(function ($message) use ($sent, $fail) {
            $this->verificationMessages[] = $message;
            if ($fail) {
                throw new \RuntimeException('private-provider-secret');
            }

            return $sent;
        });
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')->with('member@example.com')->times($count)->andReturn($pending);
        $this->partialMock(PosMailService::class, function ($mock) use ($mailer, $count) {
            $mock->shouldReceive('mailer')->times($count)->andReturn($mailer);
        });
    }
}
