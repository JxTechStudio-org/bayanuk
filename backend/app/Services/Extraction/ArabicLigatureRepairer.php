<?php

namespace App\Services\Extraction;

/**
 * Repairs Arabic words broken by PDF fonts that store the lam-alif ligature in reversed order.
 *
 * In affected PDFs every "لا" is extracted as "ال" (and "لأ" as "أل", "لإ" as "إل", "لآ" as "آل"),
 * e.g. العلاوة -> العالوة, لائحة -> الئحة, ثلاث -> ثالث, ريال -> لاير (whole word reversed).
 *
 * Instead of listing known broken words, every word gets candidates (each alif+lam pair swapped,
 * plus the whole word reversed) and a candidate is accepted only when the word list says it is a
 * clearly more frequent word than what was extracted. Words that are already valid (الكتاب, السلام)
 * have no better candidate and are left alone.
 */
class ArabicLigatureRepairer
{
    /** A swapped candidate must be at least this many times more frequent than the extracted word. */
    private const SWAP_RATIO = 5;

    /** Whole-word reversal is riskier, so it needs a much stronger signal. */
    private const REVERSE_RATIO = 100;

    private const REVERSE_MIN_FREQUENCY_FACTOR = 10;

    /** Words with more alif+lam sites than this only try single swaps and the "swap all" variant. */
    private const MAX_SITES = 6;

    /** @var list<string> */
    private const ALIF_FORMS = ['ا', 'أ', 'إ', 'آ'];

    public function __construct(
        private readonly ArabicWordList $words = new ArabicWordList,
        private readonly ?int $minFrequency = null,
    ) {}

    public function repair(string $text): string
    {
        if (! $this->words->isAvailable()) {
            $this->warnOnce();

            return $text;
        }

        return preg_replace_callback(
            '/(?:(?=\p{L})\p{Arabic})+/u',
            fn (array $match): string => $this->repairWord($match[0]),
            $text
        ) ?? $text;
    }

    public function repairWord(string $word): string
    {
        $minFrequency = $this->minFrequency();
        $current = $this->words->frequency($word);

        $best = null;
        $bestFrequency = 0;

        foreach ($this->swapCandidates($word) as $candidate) {
            $frequency = $this->words->frequency($candidate);

            if ($frequency > $bestFrequency) {
                $best = $candidate;
                $bestFrequency = $frequency;
            }
        }

        if ($best !== null && $bestFrequency >= $minFrequency && $bestFrequency >= $current * self::SWAP_RATIO) {
            return $best;
        }

        if (mb_strlen($word) >= 3) {
            $reversed = implode('', array_reverse(mb_str_split($word)));
            $frequency = $this->words->frequency($reversed);

            if ($frequency >= $minFrequency * self::REVERSE_MIN_FREQUENCY_FACTOR
                && $frequency >= $current * self::REVERSE_RATIO) {
                return $reversed;
            }
        }

        return $word;
    }

    /**
     * Every variant of the word with one or more "alif-form + lam" pairs swapped to "lam + alif-form".
     *
     * @return list<string>
     */
    private function swapCandidates(string $word): array
    {
        $chars = mb_str_split($word);
        $sites = [];

        for ($i = 0, $last = count($chars) - 1; $i < $last; $i++) {
            if ($chars[$i + 1] === 'ل' && in_array($chars[$i], self::ALIF_FORMS, true)) {
                $sites[] = $i;
            }
        }

        if ($sites === []) {
            return [];
        }

        $masks = [];
        $siteCount = count($sites);

        if ($siteCount <= self::MAX_SITES) {
            for ($mask = 1; $mask < (1 << $siteCount); $mask++) {
                $masks[] = $mask;
            }
        } else {
            for ($s = 0; $s < $siteCount; $s++) {
                $masks[] = 1 << $s;
            }
            $masks[] = (1 << $siteCount) - 1;
        }

        $candidates = [];

        foreach ($masks as $mask) {
            $variant = $chars;

            foreach ($sites as $index => $position) {
                if (($mask & (1 << $index)) !== 0) {
                    $variant[$position + 1] = $chars[$position];
                    $variant[$position] = 'ل';
                }
            }

            $candidates[] = implode('', $variant);
        }

        return $candidates;
    }

    private function warnOnce(): void
    {
        static $warned = false;

        if ($warned) {
            return;
        }

        $warned = true;

        if (function_exists('app') && app()->bound('log')) {
            app('log')->warning('Arabic word list is not installed; skipping dictionary-based ligature repair. Run `php artisan wordlist:install`.');
        }
    }

    private function minFrequency(): int
    {
        if ($this->minFrequency !== null) {
            return $this->minFrequency;
        }

        if (function_exists('app') && app()->bound('config')) {
            $configured = config('bayanuk.extraction.wordlist_min_frequency');

            if (is_numeric($configured)) {
                return (int) $configured;
            }
        }

        return 50;
    }
}
