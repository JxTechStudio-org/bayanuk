<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedTerm extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false; 

    protected $fillable = [
        'analysis_id',
        'key',
        'value_text',
        'value_number',
        'unit',
        'found',
        'page_number',
        'source_quote',
        'confidence',
        'corrected_by_user',
    ];

    protected $casts = [
        'found' => 'boolean',
        'corrected_by_user' => 'boolean',
        'value_number' => 'decimal:2',
        'confidence' => 'decimal:2',
        'page_number' => 'integer',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}