<?php

namespace App\Models;

use App\Services\EmailDeliveryService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    protected $fillable = ['username', 'full_name', 'password', 'avatar', 'email'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    }

    public function hasVerifiedEmail()
    {
        // Username-only accounts are trusted accounts created internally by staff/CLI.
        // Public registration always requires an email and cannot set verified_at.
        return $this->email === null || $this->email_verified_at !== null;
    }

    public function sendEmailVerificationNotification()
    {
        return app(EmailDeliveryService::class)->sendVerification($this);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'sold_by');
    }
}
