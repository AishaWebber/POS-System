# Submit and deploy the Laravel version

## GitHub from VS Code

1. Extract the project and open **POS-System**, the folder containing `artisan` and `composer.json`.
2. Install Git if necessary, restart VS Code, and select **Source Control → Initialize Repository**.
3. Review changes. Include raw source, README, `.env.example`, `composer.lock`, migrations, seeder, and local sample images. The included `.gitignore` excludes credentials, local databases, generated uploads, logs, sessions, and dependencies.
4. Commit with **Convert POS-System to Laravel with sample data**.
5. Click **Publish Branch**, sign in to GitHub, and name the repository **POS-System**.
6. Choose the visibility appropriate for your class. A private repository needs an invitation for your instructor.
7. Check that `app`, `database`, `public`, `resources`, `routes`, README, and `artisan` appear at the repository root. Upload the extracted source, not only a ZIP.
8. Check the Actions tab for the Laravel test workflow and copy your actual repository URL for submission.

## Laravel-capable hosting

GitHub Pages cannot execute Laravel/PHP. Use a service supporting PHP 8.2+, Composer, the required extensions, and persistent database/upload storage.

1. Install production dependencies with `composer install --no-dev --optimize-autoloader`.
2. Configure the document root as **public/**. Route non-file requests to `public/index.php`. Do not expose `.env`, `storage`, or the repository root.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, and a unique stable `APP_KEY`. Generate a key securely with `php artisan key:generate --show`; never publish it. Use `SESSION_SECURE_COOKIE=true` over HTTPS.
4. Configure the database. SQLite is the tested default and needs a persistent writable disk with an absolute `DB_DATABASE` path. Create the file before migrating. MySQL can use Laravel's standard DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD settings; verify the chosen hosted backend before submission.
5. Make `storage` and `bootstrap/cache` writable by the web process. Persist **storage/app/private/uploads** and the database across deploys/restarts. Public storage linking is not needed.
6. Run `php artisan migrate --force`, `php artisan config:cache`, and `php artisan view:cache`. Browser assets are included and need no Node build.
7. The demo seeder creates known-password sample accounts only in local/testing environments. Create a real first account using a private server terminal:

```sh
php artisan pos:create-staff owner "Your Name"
```

Keep the generated temporary password private and change it through Staff → Edit after signing in.

8. Add initial products/customers, verify image uploads and a valid sale, reject an oversell, and confirm that records persist after restart.
9. Supply your actual GitHub and hosted URLs when required. The conversion download does not publish either one.

Use `php artisan serve` for local development; configure the host's supported PHP web server for production. Keep your original CodeIgniter installation backed up separately if it contains records you want to preserve.

## Email on the hosted app

After deployment, open **Email settings** and configure a provider whose SMTP port is reachable from the host. Save a permitted sender address and SMTP/app-password credentials, then send an actual message or saved sales receipt to an address you control. Keep the deployment APP_KEY stable because it encrypts the saved SMTP password. For configuration and troubleshooting, see [EMAIL_SETUP.md](EMAIL_SETUP.md). This feature uses SMTP only. Configure Gmail with a supported App Password, STARTTLS/587 or SSL/TLS/465, and verify delivery in an inbox you control.
