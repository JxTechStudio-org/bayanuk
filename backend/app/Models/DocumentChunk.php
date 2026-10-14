<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'page_number',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
        'embedding_model',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'chunk_index' => 'integer',
        'token_count' => 'integer',
        'embedding' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
