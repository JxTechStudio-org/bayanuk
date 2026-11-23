<?php

namespace App\Services\Extraction;

class ArabicTextChecker
{
    /**
     * Common Arabic contract words and their reversed forms.
     *
     * @var array<string, string>
     */
    private const REVERSED_KEYWORDS = [
        'دقع' => 'عقد',
        'لمع' => 'عمل',
        'لمعلا' => 'العمل',
        'ةكرش' => 'شركة',
        'ةكرشلا' => 'الشركة',
        'فرطلا' => 'الطرف',
        'بتارلا' => 'الراتب',
        'فظوملا' => 'الموظف',
        'ةكلمملا' => 'المملكة',
        'ةيدوعسلا' => 'السعودية',
        'طورشلا' => 'الشروط',
        'ةبرجتلا' => 'التجربة',
        'ةيقافتلا' => 'الاتفاقية',
        'خيرات' => 'تاريخ',
        'فارطأ' => 'أطراف',
    ];

    /**
     * Check if the text needs OCR fallback (empty, scanned, broken, or reversed).
     */
    public function needsOcr(string $text, int $minChars = 30): bool
    {
        if (! $this->hasSufficientText($text, $minChars)) {
            return true;
        }

        return $this->isGarbled($text);
    }

    /**
     * Determine if extracted text has enough non-whitespace characters to be a valid text layer.
     */
    public function hasSufficientText(string $text, int $minChars = 30): bool
    {
        $stripped = preg_replace('/[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', '', $text) ?? '';

        return mb_strlen($stripped) >= $minChars;
    }

    /**
     * Determine if Arabic text in the document is garbled (either reversed or disconnected).
     */
    public function isGarbled(string $text): bool
    {
        // If there is no significant Arabic text, it cannot be garbled Arabic
        if (! $this->hasArabicContent($text)) {
            return false;
        }

        return $this->isReversed($text) || $this->isDisconnected($text);
    }

    /**
     * Check if the text contains any Arabic characters.
     */
    public function hasArabicContent(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) === 1;
    }

    /**
     * Detect if Arabic text is reversed (written Left-to-Right instead of Right-to-Left).
     */
    public function isReversed(string $text): bool
    {
        // Extract all Arabic words (length >= 2)
        preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]{2,}/u', $text, $matches);
        $words = $matches[0];

        if (count($words) === 0) {
            return false;
        }

        $totalWords = count($words);
        $reversedSignCount = 0;
        $keywordMatchCount = 0;

        foreach ($words as $word) {
            // Heuristic 1: Words starting with Taa Marbuta (ة). In valid Arabic, ة NEVER appears at word start.
            if (mb_substr($word, 0, 1) === 'ة') {
                $reversedSignCount++;
            }

            // Heuristic 2: Words ending with Al (ال) - in reversed text, the definite article 'ال' appears at the end.
            if (mb_strlen($word) >= 3 && mb_substr($word, -2) === 'لا') {
                $reversedSignCount++;
            }

            // Heuristic 3: Explicit reversed contract keywords (e.g. دقع, ةكرش, لمعلا)
            if (isset(self::REVERSED_KEYWORDS[$word])) {
                $keywordMatchCount++;
            }
        }

        // If at least 2 reversed keywords match, or if >= 10% of Arabic words exhibit reversed indicators
        if ($keywordMatchCount >= 2) {
            return true;
        }

        return ($reversedSignCount / $totalWords) >= 0.10;
    }

    /**
     * Detect if Arabic text has disconnected / broken characters (e.g. separated by spaces or presentation forms).
     */
    public function isDisconnected(string $text): bool
    {
        // 1. Check for high density of Arabic Presentation Forms (isolated glyphs from bad PDF fonts)
        preg_match_all('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text, $presentationMatches);
        $presentationCharCount = count($presentationMatches[0]);

        preg_match_all('/[\x{0600}-\x{06FF}]/u', $text, $standardArabicMatches);
        $standardCharCount = count($standardArabicMatches[0]);
        $totalArabicChars = $presentationCharCount + $standardCharCount;

        if ($totalArabicChars > 20 && ($presentationCharCount / $totalArabicChars) >= 0.20) {
            return true;
        }

        // 2. Check for sequences of single-character Arabic tokens separated by whitespace (e.g. "ع ق د   ع م ل")
        preg_match_all('/(?<=\s|^)[\x{0600}-\x{06FF}](?=\s|$)/u', $text, $singleTokens);
        $singleTokenCount = count($singleTokens[0]);

        preg_match_all('/[\x{0600}-\x{06FF}]+/u', $text, $allArabicTokens);
        $totalTokenCount = count($allArabicTokens[0]);

        if ($totalTokenCount >= 6 && ($singleTokenCount / $totalTokenCount) >= 0.30) {
            return true;
        }

        return false;
    }
}
