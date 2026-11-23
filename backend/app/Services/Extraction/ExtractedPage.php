<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionMethod;

class ExtractedPage
{
    public function __construct(
        public readonly int $pageNumber,
        public readonly string $text,
        public readonly int $charCount,
        public readonly ?ExtractionMethod $extractionMethod = null,
        public readonly bool $needsOcr = false,
    ) {}
}
