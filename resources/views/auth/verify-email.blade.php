<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm your email · POS-System</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="login-page">
<main class="login-card verification-card">
    <p class="eyebrow">POS-System</p>
    <h1>Confirm your email</h1>
    <p>Open <strong>{{ $currentUser->email }}</strong> and click <strong>Confirm email address</strong> in your POS-System email.</p>
    <p class="muted">Your account is saved. Workspace access stays locked until you confirm your email. Links expire after 60 minutes.</p>
    @if(session('success'))<div role="status" class="notice success">{{ session('success') }}</div>@endif
    @if(session('error'))<div role="alert" class="notice error">{{ session('error') }}</div>@endif
    <p class="small muted">Check your spam folder. If sending is unconfirmed, check your inbox before requesting another link. If SMTP is not configured, ask an existing staff member to configure Gmail in Email settings.</p>
    <form action="{{ route('verification.send') }}" method="post" class="stack">
        @csrf
        <button type="submit" class="button primary wide">Resend confirmation email</button>
    </form>
    <p class="small muted">You can request up to two new emails per minute. Sign in to this account before opening a confirmation link in another browser.</p>
    <form action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit" class="button wide">Sign out</button>
    </form>
</main>
</body>
</html>
