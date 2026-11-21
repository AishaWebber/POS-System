# Send actual emails through Gmail

POS-System has two sending workflows: **Email → Compose email** for ordinary messages and **Sales history → receipt → Send receipt email** for saved sales receipts. Both use SMTP. The application no longer exposes a diagnostic-email sender or a log-delivery option.

## Connect your Gmail account

1. In your Google account, enable **2-Step Verification**.
2. Open **https://myaccount.google.com/apppasswords** and create an **App Password** named POS-System. If your account does not offer App Passwords, use an eligible account or your Google Workspace administrator's approved SMTP relay. This app uses SMTP credentials rather than a Google OAuth sign-in flow.
3. Sign in to POS-System and open **Email settings**.
4. Click **Use Gmail settings**. This fills the hostname, port, and encryption:

| Field | Value |
| --- | --- |
| Sender name | POS-System or your store name |
| Sender email | Your full Gmail address |
| SMTP host | `smtp.gmail.com` |
| SMTP port | `587` |
| Encryption | **STARTTLS (required TLS)** |
| SMTP username | Your full Gmail address |
| SMTP password / app password | The Google App Password you created |

5. Enter your Gmail address in both Sender email and SMTP username, paste the App Password, and click **Save email settings**. Grouping spaces in Gmail App Passwords are removed automatically. Use your App Password rather than your normal Gmail sign-in password.
6. Open **Email → Compose email**, enter the actual recipient, subject, and message, then click **Send email**.
7. Confirm that Email history shows **Accepted by SMTP**, then check the recipient's inbox/spam folder. Gmail Sent/All Mail and bounce notices can help investigate delivery.

Gmail also supports **port 465 with SSL/TLS**; use the matching encryption setting. Gmail settings reject unencrypted mode and mismatched port/encryption combinations.

Do not send your App Password in chat or put it in source control. Enter it directly in your local or hosted POS-System app. The app needs an internet connection and outbound SMTP access to Gmail from the computer/server running Laravel.

## Send an actual sales receipt

1. Record a sale or open an existing sale under **Sales history**.
2. Find **Email this receipt** below the saved receipt.
3. Review the recipient email address. It defaults to the linked customer's current address when available; walk-in sales need an address entered manually.
4. Click **Send receipt email**.
5. The email contains the saved receipt number, customer, product, quantity, total, date, and original staff-name snapshot. The same details are included in HTML and plain-text forms.

Emailing a receipt does not record another sale or reduce inventory again. A sending error does not undo the already-recorded sale. No automatic customer email is triggered merely by saving or editing POS records.

## Email history and status

**Email → Email history** records recipient, subject, type, staff, time, and SMTP status. It stores message metadata and a payload hash rather than the full composed-message body.

- **Accepted by SMTP:** the SMTP server accepted the real message. Final inbox placement still depends on Gmail and the receiving mail service; a later bounce is possible.
- **Sending / check provider:** the attempt is in progress or was interrupted. Check provider logs or Gmail Sent/All Mail before retrying.
- **Sending unconfirmed:** the application could not confirm SMTP acceptance. No success is reported. Check the settings, Gmail delivery information, and any bounce messages before sending again.

The same submitted form key is not sent twice. Reload/open a new compose or receipt form for an intentional new send. Both send actions share a five-per-minute limit per signed-in staff account.

## Credential handling

SMTP passwords are encrypted in the database with Laravel's encryption service and are never shown back in the form. Leave the password field blank to keep a saved password; **Clear stored password** removes it. Gmail sending remains disabled until an SMTP username, App Password, sender address, and valid port/encryption combination are available.

Keep `.env`, the database, generated logs, and your `APP_KEY` private. Preserve the same `APP_KEY` during upgrades so the stored SMTP password stays readable. If it was lost or changed, re-enter and save the App Password. Validation errors do not flash SMTP passwords, and SMTP errors are recorded without raw transport debug or credential content.

Settings are used immediately by a fresh SMTP mailer for each send. The existing staff permissions apply to email settings, composing, receipt sending, and history.

## Updating an existing installation

Back up the project and database. Replace the source while preserving `.env`, `database/database.sqlite`, and generated uploads, then run:

```sh
php setup.php
php artisan optimize:clear
```

The migrations add settings and outgoing-email history tables without deleting POS records. Existing saved log-mode settings must be replaced by actual SMTP settings before sending. Do not use `migrate:fresh` for an upgrade.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Send button disabled | Save SMTP settings, including Gmail username, App Password, sender address, and the matching port/encryption. |
| Gmail rejects authentication | Confirm 2-Step Verification and an eligible App Password. Normal Gmail passwords are not used for this integration. Check any Google security notices. |
| App Password option is missing | Check account eligibility or your Workspace administrator's restrictions; use an approved SMTP account/relay. |
| Connection refused or timed out | Check internet access, `smtp.gmail.com`, port 587/465, and outbound SMTP/firewall rules on the Laravel host. |
| TLS/certificate error | Use STARTTLS/587 or SSL/TLS/465. Update PHP's trusted CA configuration if necessary; do not disable certificate verification. |
| Sender rejected | Use the authenticated Gmail address or a sender alias Google has verified for that account. |
| SMTP accepted but inbox is empty | Check recipient spelling, spam, bounce notices, and Gmail Sent/All Mail. SMTP acceptance is not final-delivery tracking. |
| Sending unconfirmed | Check provider information before retrying, because a connection interruption can leave delivery uncertain. |
| Password stopped working after deployment | Restore the original APP_KEY or enter/save the App Password again. |
| Rate limit response | Wait one minute before sending more messages. |

The sending code and actual message/receipt contents were verified with a local SMTP server. Delivery through your Gmail account can be confirmed only after you configure your credentials and a recipient you control in your installation.


## Account confirmation messages

Public registration now sends a real account-confirmation link to the registrant email using these same saved SMTP settings. Configure the sender through an existing staff account first. Unconfirmed new accounts can only confirm, resend, or sign out. Confirmation mail is recorded in Email history as Account confirmation. A sending failure keeps the account pending; it does not falsely unlock the workspace. See [REGISTRATION.md](REGISTRATION.md) for registration and email confirmation.
