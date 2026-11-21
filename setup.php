<?php

chdir(__DIR__);
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "PHP 8.2 or newer is required.\n");
    exit(1);
}
$required = ['ctype', 'curl', 'dom', 'fileinfo', 'gd', 'mbstring', 'openssl', 'PDO', 'session', 'tokenizer', 'xml'];
$missing = array_values(array_filter($required, fn ($extension) => ! extension_loaded($extension)));
if ($missing) {
    fwrite(STDERR, 'Enable these PHP extensions: '.implode(', ', $missing)."\nRun php --ini to locate the CLI configuration.\n");
    fwrite(STDERR, "For XAMPP, edit C:\\xampp\\php\\php.ini and enable the matching extension lines.\n");
    exit(1);
}
if (! file_exists('.env')) {
    copy('.env.example', '.env');
}
$commands = ['composer install --no-interaction --prefer-dist'];
$commands[] = escapeshellarg(PHP_BINARY).' artisan config:clear';
if (! preg_match('/^APP_KEY=.+$/m', file_get_contents('.env'))) {
    $commands[] = escapeshellarg(PHP_BINARY).' artisan key:generate --ansi';
}
$commands[] = escapeshellarg(PHP_BINARY).' scripts/prepare-database.php';
$commands[] = escapeshellarg(PHP_BINARY).' artisan migrate --seed --force';
foreach ($commands as $command) {
    passthru($command, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Setup stopped. Fix the error above and run php setup.php again.\n");
        exit($code);
    }
}
echo "\nReady! Run: php artisan serve\nOpen: http://127.0.0.1:8000\nSample login: Gabriel / Admin123!\n";
