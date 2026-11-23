<?php

namespace App\Services\AI;

interface AIService
{
    /**
     * Perform OCR fallback on a page image using Gemini Vision.
     */
    public function ocrFallback(string $imagePath): string;
}
