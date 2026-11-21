<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailDeliveryException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        try {
            $user = DB::transaction(function () use ($request) {
                $data = $request->validate([
                    'full_name' => ['required', 'string', 'max:100'],
                    'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
                    'username' => ['bail', 'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/D', function ($attribute, $value, $fail) {
                        if (User::whereRaw('LOWER(username) = ?', [strtolower($value)])->exists()) {
                            $fail('That username is already in use.');
                        }
                    }],
                    'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
                ]);
                if (strlen($data['password']) > 72) {
                    throw ValidationException::withMessages(['password' => 'Use a password up to 72 bytes.']);
                }

                // Only validated account fields are accepted. User's cast hashes the password.
                return User::create($data);
            });
        } catch (UniqueConstraintViolationException $e) {
            $field = User::where('email', $request->input('email'))->exists() ? 'email' : 'username';
            throw ValidationException::withMessages([$field => 'That '.$field.' is already in use.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $user->sendEmailVerificationNotification();
        } catch (\InvalidArgumentException|EmailDeliveryException $e) {
            return redirect()->route('verification.notice')->with('error', 'Your account is saved and still needs confirmation. '.$e->getMessage());
        }

        return redirect()->route('verification.notice')->with('success', 'The mail server accepted your confirmation email. Open your inbox and click Confirm email address.');
    }
}
