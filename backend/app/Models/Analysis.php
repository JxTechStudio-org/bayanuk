<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    /** @use HasFactory<AnalysisFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'status',
        'provider',
        'model',
        'prompt_version',
        'schema_version',
        'result',
        'summary',
        'summary_language',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'duration_ms',
        'error_code',
    ];

    protected $casts = [
        'status' => AnalysisStatus::class,
        'result' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:5',
        'duration_ms' => 'integer',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<ExtractedTerm, $this>
     */
    public function extractedTerms(): HasMany
    {
        return $this->hasMany(ExtractedTerm::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @return HasMany<AnalysisFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(AnalysisFeedback::class);
    }
}
