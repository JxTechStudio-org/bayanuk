<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'citations',
        'answer_found',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'citations' => 'array', 
        'answer_found' => 'boolean',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}