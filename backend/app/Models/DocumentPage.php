<?php
namespace App\Models;

use App\Enums\ExtractionMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class DocumentPage extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'page_number',
        'text',
        'char_count',
        'extraction_method',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'char_count' => 'integer',
        'extraction_method' => ExtractionMethod::class,
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}