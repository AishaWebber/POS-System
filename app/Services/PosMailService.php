<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class PosMailService
{
    public function settings(): MailSetting
    {
        $settings = MailSetting::find(1);
        if ($settings) {
            return $settings;
        }
        $settings = new MailSetting([
            'mailer' => config('mail.default') === 'smtp' ? 'smtp' : 'unconfigured',
            'from_name' => config('mail.from.name', 'POS-System'), 'from_address' => config('mail.from.address', 'hello@example.com'),
            'smtp_host' => config('mail.mailers.smtp.host'), 'smtp_port' => config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'smtp_username' => config('mail.mailers.smtp.username'), 'smtp_password' => config('mail.mailers.smtp.password'),
        ]);
        $settings->id = 1;

        return $settings;
    }

    public function ready(?MailSetting $settings = null): bool
    {
        $settings ??= $this->settings();
        if ($settings->mailer !== 'smtp' || ! filled($settings->smtp_host) || ! $settings->smtp_port || ! filled($settings->from_address)) {
            return false;
        }
        if (strtolower($settings->smtp_host) === 'smtp.gmail.com') {
            return filter_var($settings->smtp_username, FILTER_VALIDATE_EMAIL) !== false && ! empty($settings->getAttributes()['smtp_password']) && (($settings->encryption === 'tls' && $settings->smtp_port === 587) || ($settings->encryption === 'ssl' && $settings->smtp_port === 465));
        }

        return true;
    }

    public function mailer(MailSetting $settings): Mailer
    {
        if (! $this->ready($settings)) {
            throw new InvalidArgumentException('Configure SMTP in Email settings before sending an email.');
        }
        $config = [
            'transport' => 'smtp', 'scheme' => $settings->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $settings->smtp_host, 'port' => $settings->smtp_port,
            'username' => $settings->smtp_username ?: null, 'password' => $settings->smtp_password ?: null,
            'auto_tls' => $settings->encryption === 'tls', 'require_tls' => $settings->encryption === 'tls',
            'timeout' => 15, 'local_domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ];
        $mailer = Mail::build($config);
        $mailer->alwaysFrom($settings->from_address, $settings->from_name);

        return $mailer;
    }
}
