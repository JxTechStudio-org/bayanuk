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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function documentA(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_a_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function documentB(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_b_id');
    }
}
