<?php

namespace App\Http\Resources;

use App\Enums\FindingSeverity;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array (Integration Contract section 13).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $analysis = $this->latestAnalysis;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'document_type' => $this->document_type?->value,
            'language' => $this->language?->value,
            'page_count' => $this->page_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'analysis' => $analysis ? [
                'id' => $analysis->id,
                'summary' => $analysis->summary,
                'summary_language' => $analysis->summary_language,
                'terms' => $analysis->extractedTerms->map(function ($term) {
                    if (! $term->found) {
                        return [
                            'key' => $term->key,
                            'found' => false,
                        ];
                    }

                    $value = $term->value_number !== null
                        ? ($term->value_number == (int) $term->value_number ? (int) $term->value_number : (float) $term->value_number)
                        : $term->value_text;

                    $result = [
                        'key' => $term->key,
                        'value' => $value,
                        'found' => true,
                    ];

                    if ($term->unit !== null) {
                        $result['unit'] = $term->unit;
                    }

                    if ($term->page_number !== null) {
                        $result['page'] = $term->page_number;
                    }

                    if ($term->source_quote !== null) {
                        $result['quote'] = $term->source_quote;
                    }

                    return $result;
                })->values()->all(),
                'findings' => $analysis->findings
                    ->sortByDesc(fn ($f) => match ($f->severity) {
                        FindingSeverity::HIGH => 3,
                        FindingSeverity::ATTENTION => 2,
                        FindingSeverity::INFO => 1,
                    })
                    ->values()
                    ->map(fn ($f) => [
                        'category' => $f->category->value,
                        'severity' => $f->severity->value,
                        'title' => $f->title,
                        'explanation' => $f->explanation,
                        'page' => $f->page_number,
                        'quote' => $f->source_quote,
                    ])->all(),
            ] : null,
        ];
    }
}
