<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = ['mailer', 'from_name', 'from_address', 'smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password'];

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return ['smtp_password' => 'encrypted', 'smtp_port' => 'integer'];
    }
}
