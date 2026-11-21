<?php

namespace App\Http\Controllers;

use App\Services\PosMailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailSettingsController extends Controller
{
    public function edit(PosMailService $mail)
    {
        $settings = $mail->settings();

        return view('settings.email', ['title' => 'Email settings', 'record' => $settings,
            'passwordConfigured' => ! empty($settings->getAttributes()['smtp_password']), 'mailReady' => $mail->ready($settings)]);
    }

    public function update(Request $request, PosMailService $mail)
    {
        if (is_string($request->input('smtp_host'))) {
            $request->merge(['smtp_host' => strtolower(trim($request->input('smtp_host')))]);
        }
        $isGmail = $request->input('smtp_host') === 'smtp.gmail.com';
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp'])],
            'from_name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'from_address' => ['required', 'email:rfc', 'max:254'],
            'smtp_host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.:-]+$/D'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in($isGmail ? ['tls', 'ssl'] : ['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'required_if:smtp_host,smtp.gmail.com', 'string', 'max:255', Rule::when($isGmail, ['email:rfc'])],
            'smtp_password' => ['nullable', 'string', 'max:2048'], 'clear_password' => ['sometimes', 'boolean'],
        ]);
        if ($isGmail && (int) $data['smtp_port'] !== ($data['encryption'] === 'ssl' ? 465 : 587)) {
            throw ValidationException::withMessages(['smtp_port' => 'For Gmail, use port 587 with STARTTLS or port 465 with SSL/TLS.']);
        }
        $settings = $mail->settings();
        $password = $data['smtp_password'] ?? null;
        $clear = $request->boolean('clear_password');
        if ($isGmail && is_string($password)) {
            $password = preg_replace('/\s+/u', '', $password);
        }
        if ($isGmail && ! $clear && empty($password) && empty($settings->getAttributes()['smtp_password'])) {
            throw ValidationException::withMessages(['smtp_password' => 'Enter a Google App Password for Gmail SMTP.']);
        }
        unset($data['smtp_password'],$data['clear_password']);
        $settings->fill($data);
        if ($clear) {
            $settings->smtp_password = null;
        } elseif ($password !== null && $password !== '') {
            $settings->smtp_password = $password;
        }
        $settings->save();

        return redirect()->route('email.edit')->with('success', 'SMTP settings saved. Use Compose email or a saved receipt to send.');
    }
}
