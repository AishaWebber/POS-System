<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · POS-System</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="login-page">
<div class="login-wrap">
    <section class="login-intro">
        <span class="brand-icon">▦</span>
        <p class="eyebrow">POS-System</p>
        <h1>Your store.<br> Your team.<br> One workspace.</h1>
        <p>Create your staff account to manage<br>products, people, and every sale.</p>
        <div class="login-pill"><span class="live-dot"></span> Built for the everyday counter</div>
    </section>
    <section class="login-card">
        <p class="eyebrow">JOIN YOUR WORKSPACE</p>
        <h2>Create a staff account</h2>
        <p class="muted">Confirm your email to activate your staff account. Staff can manage products, customers, sales, other staff accounts, and email settings.</p>
        @if($errors->any())
            <div role="alert" class="notice error">
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form action="{{ route('register.store') }}" method="post" class="stack">
            @csrf
            <div>
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name" maxlength="100" required autofocus>
            </div>
            <div>
                <label for="email">Your email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" aria-describedby="email-help" required>
                <p id="email-help" class="small muted">Use an actual inbox you can open. We will send your account confirmation link here.</p>
            </div>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]+" aria-describedby="username-help" required>
                <p id="username-help" class="small muted">3–50 characters: letters, numbers, dots, underscores, or hyphens.</p>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password-help" required>
                <p id="password-help" class="small muted">At least 8 characters, up to 72 bytes.</p>
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
            </div>
            <button type="submit" class="button primary wide">Create account →</button>
        </form>
        <p class="small muted login-help">Already have an account? <a class="text-link" href="{{ route('login') }}">Sign in</a></p>
    </section>
</div>
</body>
</html>
