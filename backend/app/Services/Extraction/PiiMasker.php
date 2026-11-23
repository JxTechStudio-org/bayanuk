<?php

namespace App\Services\Extraction;

class PiiMasker
{
    public function __construct(
        private readonly TextNormalizer $normalizer = new TextNormalizer
    ) {}

    /**
     * Mask personal identifiers (Email, IBAN, Phone, National ID, Iqama) from text.
     */
    public function mask(string $text): string
    {
        // 1. Normalize numerals first so Eastern/Arabic-Indic digits (٠-٩) are accurately recognized
        $text = $this->normalizer->normalizeNumerals($text);

        // 2. Mask emails first to protect numbers/names inside email addresses
        $text = $this->maskEmails($text);

        // 3. Mask IBANs (Saudi and International) before general phone/ID numbers
        $text = $this->maskIbans($text);

        // 4. Mask phone numbers (Saudi and International) before 10-digit IDs
        $text = $this->maskPhones($text);

        // 5. Mask Saudi National IDs (10 digits starting with 1)
        $text = $this->maskNationalIds($text);

        // 6. Mask Saudi Iqama numbers (10 digits starting with 2)
        $text = $this->maskIqamas($text);

        return $text;
    }

    /**
     * Mask email addresses.
     */
    private function maskEmails(string $text): string
    {
        return preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u',
            '[EMAIL]',
            $text
        ) ?? $text;
    }

    /**
     * Mask International and Saudi IBANs (both standard and OCR-reversed orientations).
     * Saudi IBAN forward: SA + 2 digits + 20 alphanumeric chars (with optional spaces).
     * Saudi IBAN reversed: 20 alphanumeric chars + SA/check-digits (resulting from RTL OCR reading).
     * General IBAN: 2 uppercase letters + 2 digits + 11 to 30 alphanumeric characters.
     */
    private function maskIbans(string $text): string
    {
        // 1. Forward Saudi IBAN with any 2 check digits (continuous or formatted with spaces)
        // E.g. SA03 8000 0000 6080 1016 7519 or SA44 1234 5678 9012 3456 7890
        $text = preg_replace(
            '/\bSA\s*\d{2}(?:[ ]?[0-9A-Z]{4}){5}\b|\bSA\s*\d{22}\b/i',
            '[IBAN]',
            $text
        ) ?? $text;

        // 2. Reversed Saudi IBAN with any 2 check digits (common in RTL OCR scanning of Arabic contracts)
        // E.g. 7519 1016 6080 0000 8000 SA03 or ... 8000 .SA03 or ... 7890 SA44 or ... 44SA
        $text = preg_replace(
            '/\b(?:[0-9A-Z]{4}[ ]?){5}[\s.]*(?:SA\s*\d{2}|\d{2}\s*SA|\d{2}\s*AS|AS\s*\d{2})\b|\b[0-9A-Z]{20}[\s.]*(?:SA\s*\d{2}|\d{2}\s*SA|\d{2}\s*AS|AS\s*\d{2})\b/i',
            '[IBAN]',
            $text
        ) ?? $text;

        // 3. General International IBANs without absorbing arbitrary words/spaces
        return preg_replace(
            '/\b[A-Z]{2}\d{2}(?:[ ]?[0-9A-Z]{4}){2,7}(?:[ ]?[0-9A-Z]{1,4})?\b|\b[A-Z]{2}\d{2}[0-9A-Z]{11,30}\b/',
            '[IBAN]',
            $text
        ) ?? $text;
    }

    /**
     * Validate an IBAN checksum using the ISO 7064 MOD-97-10 algorithm.
     * Supports both canonical forward IBANs and OCR-reversed Saudi IBANs.
     */
    public static function validateIbanChecksum(string $iban): bool
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $iban) ?? '');

        // Canonical forward IBAN
        if (preg_match('/^[A-Z]{2}\d{2}/', $clean)) {
            return self::checkMod97($clean);
        }

        // Reversed Saudi IBAN
        if (preg_match('/^([0-9A-Z]{20})(SA\d{2}|\d{2}SA|\d{2}AS|AS\d{2})$/', $clean, $matches)) {
            $account = $matches[1];
            $tag = $matches[2];

            if (preg_match('/SA(\d{2})/', $tag, $m) || preg_match('/(\d{2})SA/', $tag, $m) || preg_match('/(\d{2})AS/', $tag, $m) || preg_match('/AS(\d{2})/', $tag, $m)) {
                // In RTL OCR, chunks of 4 characters are read in reverse order
                $chunks = str_split($account, 4);
                $unreversedAccount = implode('', array_reverse($chunks));
                $canonical = 'SA'.$m[1].$unreversedAccount;

                return self::checkMod97($canonical);
            }
        }

        return false;
    }

    /**
     * Helper to compute ISO 7064 MOD 97 on a canonical forward IBAN.
     */
    protected static function checkMod97(string $canonical): bool
    {
        if (strlen($canonical) < 15 || strlen($canonical) > 34) {
            return false;
        }

        $rearranged = substr($canonical, 4).substr($canonical, 0, 4);
        $numeric = '';
        $len = strlen($rearranged);

        for ($i = 0; $i < $len; $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        $remainder = 0;
        $numLen = strlen($numeric);
        for ($i = 0; $i < $numLen; $i += 7) {
            $chunk = (string) $remainder.substr($numeric, $i, 7);
            $remainder = ((int) $chunk) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Mask Saudi and international phone numbers.
     */
    private function maskPhones(string $text): string
    {
        // 1. Saudi mobile numbers:
        // Formats:
        // - Local: 0501234567, 050 123 4567, 050-123-4567
        // - International: +966501234567, +966 50 123 4567, 00966501234567, 966501234567
        $saudiMobile = '/(?<!\d)(?:(?:\+966|00966|966)[ \t-]?)?0?5\d(?:[ \t-]?\d{3}[ \t-]?\d{4}|\d{7})(?!\d)/u';
        $text = preg_replace($saudiMobile, '[PHONE]', $text) ?? $text;

        // 2. Saudi landline numbers (011..., 012..., etc.)
        // Formats: 0112345678, +966 11 234 5678, 011-234-5678
        $saudiLandline = '/(?<!\d)(?:(?:\+966|00966|966)[ \t-]?)?0?1[1-7](?:[ \t-]?\d{3}[ \t-]?\d{4}|\d{6,7})(?!\d)/u';
        $text = preg_replace($saudiLandline, '[PHONE]', $text) ?? $text;

        // 3. General international phone numbers with + or 00 prefix
        // E.g. +1 555 234 5678, +44 20 7946 0919, +971 50 123 4567
        $intlPhone = '/(?<!\d)(?:\+|00)[1-9]\d{0,2}[ \t-]?(?:\(?\d{1,4}\)?[ \t-]?)?\d{2,4}[ \t-]?\d{3,4}(?:[ \t-]?\d{1,4})?(?!\d)/u';
        $text = preg_replace($intlPhone, '[PHONE]', $text) ?? $text;

        return $text;
    }

    /**
     * Mask Saudi Citizen National ID numbers (10 digits starting with 1).
     * Formats: 1012345678, 1-01234567-8, 1012 345 678
     */
    private function maskNationalIds(string $text): string
    {
        return preg_replace(
            '/(?<!\d)(?:1\d{9}|1-\d{8}-\d|1\d{3}[ \t]\d{3}[ \t]\d{3})(?!\d)/u',
            '[NATIONAL_ID]',
            $text
        ) ?? $text;
    }

    /**
     * Mask Saudi Resident Iqama numbers (10 digits starting with 2).
     * Formats: 2012345678, 2-01234567-8, 2012 345 678
     */
    private function maskIqamas(string $text): string
    {
        return preg_replace(
            '/(?<!\d)(?:2\d{9}|2-\d{8}-\d|2\d{3}[ \t]\d{3}[ \t]\d{3})(?!\d)/u',
            '[IQAMA]',
            $text
        ) ?? $text;
    }
}
