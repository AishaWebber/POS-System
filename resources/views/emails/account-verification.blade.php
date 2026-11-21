<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Confirm your POS-System account</title></head>
<body style="font-family:Arial,sans-serif;color:#18333a;line-height:1.6">
    <h1>Confirm your email address</h1>
    <p>Hello {{ $staffName }},</p>
    <p>You registered a staff account with POS-System. Confirm that this email address belongs to you before accessing the workspace.</p>
    <p><a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 20px;background:#167d68;color:#fff;text-decoration:none;border-radius:6px">Confirm email address</a></p>
    <p>This link expires in 60 minutes. Sign in to the account you registered before opening the link.</p>
    <p>If the button does not work, copy this address into your browser:</p>
    <p>{{ $verificationUrl }}</p>
    <p>If you did not register, you can ignore this message.</p>
</body>
</html>
