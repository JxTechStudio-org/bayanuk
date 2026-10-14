<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginToken extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'token_hash',
        'expires_at',
        'used_at',
        'ip_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
