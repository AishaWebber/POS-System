<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('pos:create-staff {username} {full_name}', function () {
    $username = trim($this->argument('username'));
    $name = $this->argument('full_name');
    if (! preg_match('/^[A-Za-z0-9_.-]{3,50}$/D', $username) || trim($name) === '' || mb_strlen($name) > 100) {
        $this->error('Provide a valid username and full name.');

        return 1;
    }
    if (User::whereRaw('LOWER(username) = ?', [strtolower($username)])->exists()) {
        $this->error('That username already exists.');

        return 1;
    }
    $password = bin2hex(random_bytes(10));
    User::create(['username' => $username, 'full_name' => $name, 'password' => $password]);
    $this->info('Account created. Keep the temporary password private and change it after signing in.');
    $this->line('Username: '.$username);
    $this->line('Temporary password: '.$password);

    return 0;
})->purpose('Create an initial staff account with a generated temporary password');
