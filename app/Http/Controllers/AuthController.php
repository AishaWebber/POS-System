<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login', ['title' => 'Staff sign in']);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:50'], 'password' => ['required', 'string', 'max:200']]);
        $key = 'staff-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['username' => 'Too many sign-in attempts. Wait a minute and try again.']);
        }
        RateLimiter::hit($key, 60);
        $user = User::whereRaw('LOWER(username) = ?', [strtolower($credentials['username'])])->first();
        $credentials['username'] = $user?->username ?? $credentials['username'];
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['username' => 'The username or password is incorrect.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route(Auth::user()->hasVerifiedEmail() ? 'dashboard' : 'verification.notice'))
            ->with('success', 'Welcome back, '.Auth::user()->full_name.'.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
