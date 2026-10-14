<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisFeedback extends Model
{
    use HasUuids;

    protected $table = 'analysis_feedback'; 

    const UPDATED_AT = null; 

    protected $fillable = [
        'analysis_id',
        'rating',
        'field_key',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}