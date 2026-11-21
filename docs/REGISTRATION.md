# Create account and confirmation email

This update adds a Create account page and sends the actual account-confirmation email to the address entered during registration. It uses the application's existing SMTP settings. A new installation uses SQLite, with no MySQL or phpMyAdmin requirement.

## Run a fresh copy

Extract POS-System into a new folder, open it in VS Code, and run:

```sh
php setup.php
php artisan serve
```

Use PHP 8.2+, Composer 2, and the PHP extensions listed by setup. For the default SQLite connection, enable pdo_sqlite. Open http://127.0.0.1:8000. Sign in with the existing Gabriel / Admin123! sample account to configure SMTP before inviting registrations. No database server or frontend build is required for this default.

## Configure the sending account once

Open Email settings and save the real SMTP settings belonging to the application's sending account. Gmail uses smtp.gmail.com, port 587, STARTTLS, the full Google account address, and an eligible Google App Password. Gmail accounts generally require 2-Step Verification for App Passwords. Other SMTP providers can use their own host/port/TLS and credentials. The sender account is separate from each registrant's recipient inbox. Never paste SMTP credentials into chat or commit .env to Git.

## Register and confirm

1. Sign out. On the login screen, choose Create account.
2. Enter full name, an email inbox you own, unique username, password, and matching password confirmation.
3. Submit the form. The system saves the account with email_verified_at set to NULL and sends a real confirmation message to that email address. The new session is restricted to confirmation, resend, and logout until confirmed.
4. Open the email and click Confirm email address. Use the same browser, or sign in to that account when prompted in another browser. The signed link expires after 60 minutes.
5. A valid confirmation sets email_verified_at and unlocks the workspace. The Staff listing also shows the address and confirmation status.

Email history records the confirmation attempt as Account confirmation. SMTP acceptance means the server accepted the message; it does not guarantee final inbox placement. An incorrect or nonexistent recipient cannot receive a confirmation. The application checks email syntax and confirms inbox ownership, without claiming a university-domain or identity check.

If SMTP is missing or sending fails, the account stays saved and pending. Use an existing staff account to correct the SMTP settings, then return to the pending account and resend. Check inbox/spam before retrying an unconfirmed sending attempt. Do not register duplicate accounts to retry delivery.

Registration is limited to five submissions per minute per IP; resends to two per minute per account. Passwords are hashed. Usernames are unique regardless of capitalization, and email is normalized before checking uniqueness. Forged, expired, wrong-email, and wrong-user verification links cannot activate the account. The server ignores submitted verification timestamps.

Registered and confirmed accounts have the same staff permissions as other users; confirmation verifies inbox ownership, not approval from a store owner. Existing and internally provisioned username-only staff remain trusted accounts and continue working without fictitious email addresses.

## Update an existing installation

Back up the project and database. Replace the source while preserving .env, the database, and generated uploads. Run php setup.php and php artisan optimize:clear, then restart the app. The migration adds nullable email and email_verified_at columns without resetting users, products, inventory, or sales. Do not use migrate:fresh or regenerate an existing APP_KEY.

Setup preserves the existing database connection. If your .env already selects MySQL, that installation still needs its existing MySQL server; this update does not convert or move its records. For a separate fresh SQLite installation without a database server, extract the ZIP into a new folder and let setup copy .env.example. Do not overwrite an existing database to change engines.

## Link and email troubleshooting

- If no message arrives, check the recipient spelling, spam folder, and sender's SMTP settings. Gmail needs an App Password rather than the ordinary password.
- If a link expires, sign in and request a fresh one from /email/verify.
- If opening on another browser, sign in to the same account. The app returns to the confirmation link.
- A link using 127.0.0.1 must be opened on the computer running Laravel. For remote users, use a reachable HTTPS deployment with its correct APP_URL, then generate new links there.
- Keep the application key and hostname/port consistent. A changed key invalidates signatures and may prevent decrypting stored SMTP credentials.
- If the wrong inbox was entered, ask existing staff to remove that pending account through Staff (if unreferenced), then register with the correct inbox.

## Code flow

RegistrationController validates the fields and creates the User. Its transaction commits before sending. EmailDeliveryService builds a temporary signed verification URL, prepares AccountVerificationMail, and sends it through the existing SMTP-only PosMailService. Sending metadata is saved without storing the link in email history. EmailVerificationController uses Laravel EmailVerificationRequest to validate the account, email hash, signature, and expiration. User implements MustVerifyEmail; verified middleware blocks workspace access for pending public registrations.

## Verification

Automated checks cover successful registration, password hashing/confirmation, invalid/duplicate input, SMTP-only sending, pending access restrictions, valid/repeated/expired/altered/wrong-user links, resend limits, login in another browser, sending failures, and compatibility with existing staff. SMTP transport and signed-link activation were also exercised through a controlled local capture server. External delivery through your own provider requires your real credentials and checking the intended inbox.
