<?php

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $name = config('database.default');
    $config = app('db')->connection($name)->getConfig();
    $driver = $config['driver'];
    $extension = $driver === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
    if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
        throw new RuntimeException('Setup supports MySQL/MariaDB or SQLite. Check DB_CONNECTION in .env.');
    }
    if (! extension_loaded($extension)) {
        throw new RuntimeException('Enable extension='.$extension.' in the CLI php.ini shown by php --ini, then retry setup.');
    }
    if ($driver === 'sqlite') {
        $path = $config['database'];
        if ($path !== ':memory:' && ! is_file($path) && ! touch($path)) {
            throw new RuntimeException('Cannot create the configured SQLite file. Check its path and permissions.');
        }
        echo "SQLite is ready. Existing SQLite installations stay on SQLite until you change .env.\n";
        exit(0);
    }
    try {
        app('db')->connection($name)->getPdo();
        echo "MySQL/MariaDB database is available. Existing records will be preserved.\n";
        exit(0);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1049) {
            throw $e;
        }
    }
    $database = $config['database'];
    if (! is_string($database) || ! preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $database)) {
        throw new RuntimeException('Create your database in phpMyAdmin first, or use a DB_DATABASE containing only letters, digits, and underscores.');
    }
    $dsn = ! empty($config['unix_socket'])
        ? 'mysql:unix_socket='.$config['unix_socket'].';charset=utf8mb4'
        : 'mysql:host='.$config['host'].';port='.(int) $config['port'].';charset=utf8mb4';
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Created MySQL/MariaDB database. Migrations will create the application tables.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Database setup failed: '.$e->getMessage()."\nFor XAMPP, start MySQL and check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env. You can create the database in phpMyAdmin if your account cannot create databases.\n");
    exit(1);
}
