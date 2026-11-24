<?php

namespace App\Services\Extraction;

/**
 * Read-only Arabic word frequency list (one "word<whitespace>count" pair per line).
 *
 * The list itself is NOT part of the repository: it is installed per environment with
 * `php artisan wordlist:install` (see resources/NOTICE-arabic-wordlist.md for the licence).
 * When no list is installed every lookup returns 0 and isAvailable() is false, so callers
 * can degrade gracefully instead of failing.
 */
class ArabicWordList
{
    /**
     * Arabic clitics that may be glued to a dictionary word (conjunctions, prepositions, article).
     * Longest first so "وال" is tried before "و".
     *
     * @var list<string>
     */
    private const PREFIXES = ['ولل', 'وال', 'فال', 'بال', 'كال', 'وب', 'ول', 'فب', 'فل', 'لل', 'ال', 'و', 'ف', 'ب', 'ك', 'ل'];

    /** A word that only matches after stripping a clitic counts for 1/N of the stripped word's frequency. */
    private const PREFIX_PENALTY = 20;

    /**
     * Loaded lists keyed by file path, so each PHP process parses a list only once.
     *
     * @var array<string, array<string, int>>
     */
    private static array $cache = [];

    public function __construct(private readonly ?string $path = null) {}

    public static function defaultPath(): string
    {
        if (function_exists('app') && app()->bound('config')) {
            $configured = config('bayanuk.extraction.wordlist_path');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return dirname(__DIR__, 3).'/storage/app/dictionary/arabic_wordlist.tsv';
    }

    public function isAvailable(): bool
    {
        return $this->words() !== [];
    }

    /**
     * Frequency of a word, falling back to the word minus one clitic prefix (with a penalty).
     */
    public function frequency(string $word): int
    {
        $words = $this->words();

        if (isset($words[$word])) {
            return $words[$word];
        }

        $best = 0;

        foreach (self::PREFIXES as $prefix) {
            if (! str_starts_with($word, $prefix)) {
                continue;
            }

            $rest = substr($word, strlen($prefix));

            if (mb_strlen($rest) >= 3 && isset($words[$rest])) {
                $best = max($best, intdiv($words[$rest], self::PREFIX_PENALTY));
            }
        }

        return $best;
    }

    /**
     * @return array<string, int>
     */
    private function words(): array
    {
        $path = $this->path ?? self::defaultPath();

        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $words = [];

        while (($line = fgets($handle)) !== false) {
            $parts = preg_split('/\s+/u', trim($line));

            if ($parts !== false && count($parts) >= 2 && $parts[0] !== '') {
                $words[$parts[0]] = (int) $parts[1];
            }
        }

        fclose($handle);

        // An empty/unreadable list is not cached so installing it later is picked up without a restart.
        if ($words === []) {
            return [];
        }

        return self::$cache[$path] = $words;
    }
}
