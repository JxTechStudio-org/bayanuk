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
     * Common broken Arabic words resulting from reversed Lam-Alif ligatures in PDF fonts.
     *
     * @var array<string, string>
     */
    private const BROKEN_LIGATURE_WORDS = [
        '/\bإال\b/u' => 'إلا',
        '/\bخالل\b/u' => 'خلال',
        '/\bثالثين\b/u' => 'ثلاثين',
        '/\bثالث\s+(دفعات|شهور|أشهر|سنوات|أيام|ساعات|مرات|أجزاء|عقود|أقساط)\b/u' => 'ثلاث $1',
        '/\bاستالم\b/u' => 'استلام',
        '/\bعبدهللا\b/u' => 'عبدالله',
        '/\bألنظمة\b/u' => 'لأنظمة',
        '/\bألي\b/u' => 'لأي',
        '/\bألجل\b/u' => 'لأجل',
        '/\bألنه\b/u' => 'لأنه',
        '/\bألن\b/u' => 'لأن',
        '/\bالختبار\b/u' => 'لاختبار',
        '/\bااللتزام\b/u' => 'الالتزام',
        '/\bااللتزامات\b/u' => 'الالتزامات',
        '/\bاالتفاق\b/u' => 'الاتفاق',
        '/\bاإلخالل\b/u' => 'الإخلال',
        '/\bالإخالل\b/u' => 'الإخلال',
        '/\bوال\s+(يحق|يجوز|تعد|تُعد|يمكن|يلتزم|يتحمل|يقل|يزيد|يتجاوز|يعتبر|ينشأ|يترتب|يخل|يؤثر)\b/u' => 'ولا $1',
        '/\bال\s+(يحق|يجوز|تعد|تُعد|يمكن|يلتزم|يتحمل|يقل|يزيد|يتجاوز|يعتبر|ينشأ|يترتب|يخل|يؤثر)\b/u' => 'لا $1',
    ];

    /**
     * Normalize all numerals, hidden directional characters, broken ligatures, and whitespace in the given text.
     */
    public function normalize(string $text): string
    {
        $text = $this->stripInvisibleFormatting($text);
        $text = $this->normalizeArabicLigatures($text);
        $text = $this->normalizeNumerals($text);

        return $this->normalizeWhitespace($text);
    }

    /**
     * Remove invisible directional markers and zero-width formatting characters.
     * Covers:
     * - U+200B to U+200F (Zero-width spaces/joiners, LTR mark, RTL mark)
     * - U+202A to U+202E (LRE, RLE, PDF, LRO, RLO)
     * - U+2066 to U+2069 (LRI, RLI, FSI, PDI)
     * - U+FEFF (Byte Order Mark / Zero-width no-break space)
     */
    public function stripInvisibleFormatting(string $text): string
    {
        return preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text) ?? $text;
    }

    /**
     * Normalize broken Arabic ligatures caused by PDF text layer glyph-order issues.
     * E.g. 'األول' -> 'الأول', 'إال' -> 'إلا', 'خالل' -> 'خلال', etc.
     */
    public function normalizeArabicLigatures(string $text): string
    {
        // 1. Definite article followed by Alif with Hamza on top: األ -> الأ (e.g. األول -> الأول, األجر -> الأجر)
        $text = str_replace('األ', 'الأ', $text);

        // 2. Definite article followed by Alif with Hamza below: اإل -> الإ (e.g. اإلشعار -> الإشعار, اإللغاء -> الإلغاء)
        $text = str_replace('اإل', 'الإ', $text);

        // 3. Definite article followed by Madda: اآل -> الآ (e.g. اآلخر -> الآخر, اآليبان -> الآيبان)
        $text = str_replace('اآل', 'الآ', $text);

        // 4. Common inverted Lam-Alif words and particles
        return preg_replace(
            array_keys(self::BROKEN_LIGATURE_WORDS),
            array_values(self::BROKEN_LIGATURE_WORDS),
            $text
        ) ?? $text;
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
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text) ?? $text;

        // 3. Collapse multiple horizontal spaces and tabs into a single space (while keeping newlines)
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

        // 4. Trim horizontal spaces around each line
        $lines = explode("\n", $text);
        $trimmedLines = array_map(static fn (string $line): string => trim($line, " \t"), $lines);
        $text = implode("\n", $trimmedLines);

        // 5. Collapse 3 or more consecutive newlines into 2 (one blank line)
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
