# Arabic word list notice

`php artisan wordlist:install` builds the word list used to repair Arabic words broken by reversed
lam-alif ligatures in PDF text layers (`App\Services\Extraction\ArabicLigatureRepairer`).

**The word list is not stored in this repository.** It is installed per environment into
`storage/app/dictionary/` (git-ignored). The test suite uses a small hand-written list in
`tests/Fixtures/arabic_wordlist_test.tsv` that is not derived from any third-party data.

## Recommended source

CAMeL Arabic Frequency Lists (use the Modern Standard Arabic file, `MSA_freq_lists`):
https://github.com/CAMeL-Lab/Camel_Arabic_Frequency_Lists

Licence: Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0),
https://creativecommons.org/licenses/by-sa/4.0/

Attribution (required by the licence): if you use these lists, cite

> Go Inoue, Bashar Alhafni, Nurpeiis Baimukan, Houda Bouamor, and Nizar Habash. 2021.
> The Interplay of Variant, Size, and Task Type in Arabic Pre-trained Language Models.
> In Proceedings of the Sixth Arabic Natural Language Processing Workshop, pages 92–104,
> Kyiv, Ukraine (Virtual). Association for Computational Linguistics.

ShareAlike note: do not commit the downloaded or trimmed list to this repository, and do not
redistribute it, unless the result is shared under CC BY-SA 4.0.

## Install

```bash
php artisan wordlist:install path/to/MSA_freq_lists.tsv   # or a .zip, or an https URL
php artisan horizon:terminate                             # queue workers reload the list
```
