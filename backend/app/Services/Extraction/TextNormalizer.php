<?php

namespace App\Services\Extraction;

class TextNormalizer
{
    /**
     * Map of Eastern Arabic-Indic numerals to Western Arabic numerals.
     *
     * @var array<string, string>
     */
    private const NUMERAL_MAP = [
        // Eastern Arabic-Indic (Arabic standard)
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
        // Persian / Urdu variants
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ];

    /**
     * Normalize all numerals and whitespace in the given text.
     */
    public function normalize(string $text): string
    {
        $text = $this->normalizeNumerals($text);

        return $this->normalizeWhitespace($text);
    }

    /**
     * Convert Eastern Arabic-Indic and Persian numerals (٠-٩, ۰-۹) to standard Western digits (0-9).
     */
    public function normalizeNumerals(string $text): string
    {
        return strtr($text, self::NUMERAL_MAP);
    }

    /**
     * Normalize horizontal spaces, line endings, and consecutive blank lines.
     */
    public function normalizeWhitespace(string $text): string
    {
        // 1. Standardize line endings to \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 2. Replace non-breaking spaces, thin spaces, and mathematical spaces with regular space
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $text) ?? $text;

        // 3. Remove zero-width spaces / joiners if they appear in isolation
        $text = preg_replace('/[\x{200C}\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        // 4. Collapse multiple horizontal spaces and tabs into a single space (while keeping newlines)
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

        // 5. Trim horizontal spaces around each line
        $lines = explode("\n", $text);
        $trimmedLines = array_map(static fn (string $line): string => trim($line, " \t"), $lines);
        $text = implode("\n", $trimmedLines);

        // 6. Collapse 3 or more consecutive newlines into 2 (one blank line)
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
