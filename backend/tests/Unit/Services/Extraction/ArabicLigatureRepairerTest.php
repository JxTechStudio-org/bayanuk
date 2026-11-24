<?php

use App\Services\Extraction\ArabicLigatureRepairer;
use App\Services\Extraction\ArabicWordList;
use App\Services\Extraction\TextNormalizer;

beforeEach(function () {
    $wordList = new ArabicWordList(__DIR__.'/../../../Fixtures/arabic_wordlist_test.tsv');
    $this->repairer = new ArabicLigatureRepairer($wordList);
    $this->normalizer = new TextNormalizer($this->repairer);
});

it('repairs the common contract words reported in the PR review screenshot', function (string $broken, string $expected) {
    expect($this->normalizer->normalize($broken))->toBe($expected);
})->with([
    'العالوة السنوية' => ['العالوة السنوية', 'العلاوة السنوية'],
    'صالحية العقد' => ['صالحية العقد', 'صلاحية العقد'],
    'الئحة تنظيم العمل' => ['الئحة تنظيم العمل', 'لائحة تنظيم العمل'],
    'شهادة إخالء طرف' => ['شهادة إخالء طرف', 'شهادة إخلاء طرف'],
    'السالمة المهنية' => ['السالمة المهنية', 'السلامة المهنية'],
    'في حال الخالف' => ['في حال الخالف', 'في حال الخلاف'],
    'إعالن' => ['إعالن', 'إعلان'],
    'وال تحتسب فترة التجربة' => ['وال تحتسب فترة التجربة', 'ولا تحتسب فترة التجربة'],
    'ال تقل عن' => ['ال تقل عن', 'لا تقل عن'],
    'مالحظات' => ['مالحظات', 'ملاحظات'],
    'عالقة العمل' => ['عالقة العمل', 'علاقة العمل'],
    'العالج الطبي' => ['العالج الطبي', 'العلاج الطبي'],
    'االستقالة' => ['االستقالة', 'الاستقالة'],
    'لاير سعودي' => ['لاير سعودي', 'ريال سعودي'],
    'خالل ثالثين يوما' => ['خالل ثالثين يوما', 'خلال ثلاثين يوما'],
    'استالم' => ['استالم', 'استلام'],
    'ألي طرف' => ['ألي طرف', 'لأي طرف'],
    'Allah (reversed)' => ['عبدهللا وهللا', 'عبدالله والله'],
]);

it('does not touch valid words even when a swapped form also exists in the word list', function (string $valid) {
    expect($this->normalizer->normalize($valid))->toBe($valid);
})->with([
    'الكتاب' => 'الكتاب',
    'العمل' => 'العمل',
    'الله' => 'الله',
    'حال' => 'حال',
    'عمال' => 'عمال',
    'سالم (a real word, only slightly rarer than سلام)' => 'سالم',
    'unknown words' => 'الصنعاني والمهندسة',
]);

it('leaves the text untouched and does not fail when no word list is installed', function () {
    $repairer = new ArabicLigatureRepairer(new ArabicWordList(__DIR__.'/does-not-exist.tsv'));

    expect($repairer->repair('العالوة وال تقل'))->toBe('العالوة وال تقل');
});

it('only swaps alif+lam pairs, so words are rebuilt from their own letters', function () {
    expect($this->repairer->repairWord('العالوة'))->toBe('العلاوة')
        ->and($this->repairer->repairWord('االستقالة'))->toBe('الاستقالة');
});
