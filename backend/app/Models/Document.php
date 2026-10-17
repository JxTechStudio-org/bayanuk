<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\DeletionReason;
use App\Enums\DocumentLanguage;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'guest_token_hash',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
        'storage_key',
        'page_count',
        'language',
        'document_type',
        'status',
        'status_detail',
        'is_scanned',
        'processing_started_at',
        'processing_finished_at',
        'expires_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_count' => 'integer',
        'is_scanned' => 'boolean',
        'status' => DocumentStatus::class,
        'document_type' => DocumentType::class,
        'language' => DocumentLanguage::class,
        'processing_started_at' => 'datetime',
        'processing_finished_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(Analysis::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn (Builder $q) => $q->where('status', AnalysisStatus::SUCCEEDED->value),
        );
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    protected static function booted()
    {
        // automatically create an audit log entry when a document is deleted
        static::deleted(function ($document) {
            DeletionLog::create([
                'document_id' => $document->id,
                'reason' => DeletionReason::USER_REQUEST,
                'file_deleted' => true,
                'deleted_at' => now(),
            ]);
        });
    }
}