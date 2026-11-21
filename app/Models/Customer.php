<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['full_name', 'email', 'phone'];

    protected function casts(): array
    {
        return [];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
