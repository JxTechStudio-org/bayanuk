<?php

namespace App\Console\Commands;

use App\Services\Extraction\ArabicWordList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Builds the Arabic word list used for ligature repair from a frequency list
 * (tab-separated "word<TAB>count", most frequent first, e.g. the CAMeL Arabic Frequency Lists).
 *
 * The result is stored outside git (storage/app/dictionary) and only keeps plain Arabic words.
 */
class InstallArabicWordList extends Command
{
    protected $signature = 'wordlist:install
        {source : Local .tsv/.zip file or an http(s) URL of a "word<TAB>count" frequency list}
        {--limit=200000 : Maximum number of words to keep (the file is read most-frequent first)}
        {--min-frequency=50 : Skip words rarer than this}';

    protected $description = 'Install the Arabic word list used to repair broken ligatures in extracted PDF text';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $limit = max(1, (int) $this->option('limit'));
        $minFrequency = max(0, (int) $this->option('min-frequency'));

        $downloaded = null;

        if (preg_match('#^https?://#i', $source) === 1) {
            $downloaded = tempnam(sys_get_temp_dir(), 'wordlist');
            if (preg_match('/\.zip(?:[?#]|$)/i', $source) === 1) {
                $zipPath = $downloaded.'.zip';
                rename($downloaded, $zipPath);
                $downloaded = $zipPath;
            }
            $this->info("Downloading {$source} ...");

            $response = Http::timeout(900)->sink($downloaded)->get($source);

            if (! $response->successful()) {
                File::delete($downloaded);
                $this->error("Download failed (HTTP {$response->status()}).");

                return self::FAILURE;
            }

            $file = $downloaded;
        } else {
            $file = $source;
        }

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $input = $this->openInput($file);

        if ($input === null) {
            if ($downloaded !== null) {
                File::delete($downloaded);
            }
            $this->error('Could not read the word list (expected a .tsv file or a .zip containing one).');

            return self::FAILURE;
        }

        $destination = ArabicWordList::defaultPath();
        File::ensureDirectoryExists(dirname($destination));
        $temporary = $destination.'.tmp';
        $output = fopen($temporary, 'w');

        if ($output === false) {
            fclose($input);
            $this->error("Cannot write to {$temporary}");

            return self::FAILURE;
        }

        $kept = 0;

        while ($kept < $limit && ($line = fgets($input)) !== false) {
            $parts = preg_split('/\s+/u', trim($line));

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            [$word, $count] = $parts;

            // Plain Arabic letters only: no digits, Latin, punctuation or diacritics.
            if (preg_match('/^(?:(?=\p{L})\p{Arabic}){2,25}$/u', $word) !== 1 || (int) $count < $minFrequency) {
                continue;
            }

            fwrite($output, $word."\t".(int) $count."\n");
            $kept++;
        }

        fclose($input);
        fclose($output);

        if ($downloaded !== null) {
            File::delete($downloaded);
        }

        if ($kept === 0) {
            File::delete($temporary);
            $this->error('No usable Arabic words found; the existing word list was not changed.');

            return self::FAILURE;
        }

        File::move($temporary, $destination);

        $this->info("Installed {$kept} words to {$destination}");
        $this->line('Restart queue workers (php artisan horizon:terminate) so they load the new list.');

        return self::SUCCESS;
    }

    /**
     * @return resource|null
     */
    private function openInput(string $file)
    {
        $isZip = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'zip'
            || @file_get_contents($file, false, null, 0, 4) === "PK\x03\x04";

        if (! $isZip) {
            $handle = fopen($file, 'r');

            return $handle === false ? null : $handle;
        }

        $zip = new ZipArchive;

        if ($zip->open($file) !== true) {
            return null;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_ends_with(strtolower($name), '.tsv')
                && ! str_starts_with($name, '__MACOSX')
                && ! str_starts_with(basename($name), '.')) {
                $zip->close();
                $stream = fopen('zip://'.$file.'#'.$name, 'r');

                return $stream === false ? null : $stream;
            }
        }

        $zip->close();

        return null;
    }
}
