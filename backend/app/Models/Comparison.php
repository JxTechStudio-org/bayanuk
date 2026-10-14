<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comparison extends Model
{
    use HasUuids;

    const UPDATED_AT = null; 

    protected $fillable = [
        'user_id',
        'document_a_id',
        'document_b_id',
        'result',
        'summary',
    ];

    protected $casts = [
        'result' => 'array', 
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentA(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_a_id');
    }

    public function documentB(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_b_id');
    }
}