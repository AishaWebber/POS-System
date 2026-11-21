<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingEmail extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload_hash', 'request_key'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
