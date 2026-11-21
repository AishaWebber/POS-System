<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailDeliveryException;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('dashboard')->with('success', 'Your email address is confirmed. Welcome to POS-System!');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\InvalidArgumentException|EmailDeliveryException $e) {
            return redirect()->route('verification.notice')->with('error', $e->getMessage());
        }

        return redirect()->route('verification.notice')->with('success', 'The mail server accepted your confirmation email. Check your inbox and spam folder.');
    }
}
