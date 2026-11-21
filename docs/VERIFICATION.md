# Verification

The registration update was checked with PHP 8.4 and dependencies resolved for PHP 8.2. The GitHub Actions workflow targets PHP 8.2 with isolated SQLite tests.

The application test suite passed **71 tests with 511 assertions** on isolated SQLite. It covers registration, validation, password hashing, signed-link email confirmation, duplicate input, resend limits, blocked pending accounts, SMTP-only delivery, safe failure handling, and existing staff compatibility, along with the existing CRUD, images, stock, sales, and receipt workflows.

The confirmation message was transmitted to a controlled local SMTP server and its recipient, subject, and signed URL were inspected. Opening the link activated the account. This is not a claim of delivery through your personal Gmail account. Configure your real sending credentials and confirm receipt in the target inbox.

Source packages exclude .env, live databases, generated uploads, sessions, logs, and dependencies. The registration migration adds nullable email and email_verified_at fields and preserves existing records. Fresh installations use SQLite.
