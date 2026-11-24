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
     * Allah's name is extracted as the whole word reversed (هللا), also when prefixed (عبدهللا, وهللا, بهللا).
     * No Arabic word ends with "هللا", so this needs no dictionary signal.
     *
     * @var array<string, string>
     */
    private const FIXED_BROKEN_WORDS = [
        '/هللا(?!\p{L})/u' => 'الله',
    ];

    private ?ArabicLigatureRepairer $repairer;

    public function __construct(?ArabicLigatureRepairer $repairer = null)
    {
        $this->repairer = $repairer;
    }

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
        $marks = '[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+';

        // A mark sitting between a digit/Latin run and Arabic letters (either direction) is what visually
        // separates them (e.g. "12000<RLM>ريال"), so replace it with a space instead of gluing them together.
        // Only Arabic *letters* count (\p{Arabic} alone also matches punctuation such as "،").
        $text = preg_replace(
            [
                '/(?<=[\p{Nd}\p{Latin}])'.$marks.'(?=\p{L})(?=\p{Arabic})/u',
                '/(?<=\p{L})(?<=\p{Arabic})'.$marks.'(?=[\p{Nd}\p{Latin}])/u',
            ],
            ' ',
            $text
        ) ?? $text;

        // Everywhere else (e.g. between two Arabic letters) the marks are removed without leaving a gap.
        return preg_replace('/'.$marks.'/u', '', $text) ?? $text;
    }

    /**
     * Normalize broken Arabic ligatures caused by PDF text layer glyph-order issues.
     * E.g. 'األول' -> 'الأول', 'إال' -> 'إلا', 'خالل' -> 'خلال', 'العالوة' -> 'العلاوة', etc.
     */
    public function normalizeArabicLigatures(string $text): string
    {
        // 1. Definite article followed by Alif with Hamza on top: األ -> الأ (e.g. األول -> الأول, األجر -> الأجر)
        $text = str_replace('األ', 'الأ', $text);

        // 2. Definite article followed by Alif with Hamza below: اإل -> الإ (e.g. اإلشعار -> الإشعار, اإللغاء -> الإلغاء)
        $text = str_replace('اإل', 'الإ', $text);

        // 3. Definite article followed by Madda: اآل -> الآ (e.g. اآلخر -> الآخر, اآليبان -> الآيبان)
        $text = str_replace('اآل', 'الآ', $text);

        // 4. Every other alif+lam / whole-word inversion (إال, خالل, العالوة, الئحة, لاير...) is repaired by
        //    trying candidates and keeping the one the Arabic word list says is clearly the real word.
        $text = ($this->repairer ??= new ArabicLigatureRepairer)->repair($text);

        // 5. Fixed whole-word inversions that need no dictionary signal
        return preg_replace(
            array_keys(self::FIXED_BROKEN_WORDS),
            array_values(self::FIXED_BROKEN_WORDS),
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
