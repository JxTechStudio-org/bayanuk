<?php

use App\Services\Extraction\ArabicWordList;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/wordlist-install-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    config(['bayanuk.extraction.wordlist_path' => $this->dir.'/out/arabic_wordlist.tsv']);

    $this->source = $this->dir.'/source.tsv';
    file_put_contents($this->source, implode("\n", [
        "في\t1000",
        "العلاوة\t900",
        "abc\t800",          // Latin: skipped
        "١٢٣\t700",          // digits: skipped
        "أ\t600",            // single letter: skipped
        "ملاحظات\t500",
        "نادرة\t10",         // below min frequency: skipped
        "ريال\t400",
        '',
    ]));
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('installs a clean word list from a local frequency file', function () {
    $this->artisan('wordlist:install', ['source' => $this->source, '--min-frequency' => 50])
        ->assertSuccessful();

    $installed = file_get_contents($this->dir.'/out/arabic_wordlist.tsv');

    expect($installed)->toBe("في\t1000\nالعلاوة\t900\nملاحظات\t500\nريال\t400\n")
        ->and((new ArabicWordList($this->dir.'/out/arabic_wordlist.tsv'))->frequency('ريال'))->toBe(400);
});

it('keeps only the most frequent words up to the limit', function () {
    $this->artisan('wordlist:install', ['source' => $this->source, '--limit' => 2])
        ->assertSuccessful();

    expect(file_get_contents($this->dir.'/out/arabic_wordlist.tsv'))->toBe("في\t1000\nالعلاوة\t900\n");
});

it('installs from a zip archive that contains a tsv file', function () {
    $zipPath = $this->dir.'/source.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile($this->source, 'MSA_freq_lists.tsv');
    $zip->close();

    $this->artisan('wordlist:install', ['source' => $zipPath])->assertSuccessful();

    expect(file_get_contents($this->dir.'/out/arabic_wordlist.tsv'))->toContain("ريال\t400");
});

it('fails without touching an existing word list when the source has no usable words', function () {
    File::ensureDirectoryExists($this->dir.'/out');
    file_put_contents($this->dir.'/out/arabic_wordlist.tsv', "قديم\t100\n");
    file_put_contents($this->source, "abc\t1\n");

    $this->artisan('wordlist:install', ['source' => $this->source])->assertFailed();

    expect(file_get_contents($this->dir.'/out/arabic_wordlist.tsv'))->toBe("قديم\t100\n");
});

it('fails when the source file does not exist', function () {
    $this->artisan('wordlist:install', ['source' => $this->dir.'/missing.tsv'])->assertFailed();
});
