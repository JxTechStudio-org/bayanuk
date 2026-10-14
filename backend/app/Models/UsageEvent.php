<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'guest_token_hash',
        'ip_hash',
        'event',
        'input_tokens',
        'output_tokens',
        'cost_usd',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:5',
    ];
}