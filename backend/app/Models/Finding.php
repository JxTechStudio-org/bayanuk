<?php

namespace App\Models;

use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Enums\FindingSource;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'analysis_id',
        'category',
        'severity',
        'source',
        'rule_id',
        'title',
        'explanation',
        'page_number',
        'source_quote',
        'sort_order',
    ];

    protected $casts = [
        'severity' => FindingSeverity::class,
        'source' => FindingSource::class,
        'category' => FindingCategory::class,
        'page_number' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Analysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
