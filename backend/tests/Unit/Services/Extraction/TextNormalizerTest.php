<?php

use App\Services\Extraction\TextNormalizer;

beforeEach(function () {
    $this->normalizer = new TextNormalizer;
});

it('converts Eastern Arabic-Indic numerals to Western digits', function () {
    $arabicIndic = '٠١٢٣٤٥٦٧٨٩';
    expect($this->normalizer->normalizeNumerals($arabicIndic))->toBe('0123456789');
});

it('converts Persian numerals to Western digits', function () {
    $persian = '۰۱۲۳۴۵۶۷۸۹';
    expect($this->normalizer->normalizeNumerals($persian))->toBe('0123456789');
});

it('converts mixed numerals inside Arabic contract clauses', function () {
    $input = 'الراتب الأساسي: ٨٥٠٠ ريال، وبدل السكن: ٢١٢٥ ريال لمدة ١٢ شهراً.';
    $expected = 'الراتب الأساسي: 8500 ريال، وبدل السكن: 2125 ريال لمدة 12 شهراً.';

    expect($this->normalizer->normalizeNumerals($input))->toBe($expected);
});

it('leaves existing Western digits untouched', function () {
    $input = 'Basic Salary: 10000 SAR for 24 months, starting on 2026-11-01.';
    expect($this->normalizer->normalizeNumerals($input))->toBe($input);
});

it('normalizes multiple spaces and tabs into a single space', function () {
    $input = "البند الأول:   \t  تحديد الراتب    الشهري.";
    $expected = 'البند الأول: تحديد الراتب الشهري.';

    expect($this->normalizer->normalizeWhitespace($input))->toBe($expected);
});

it('converts Windows CRLF and CR to LF line endings', function () {
    $input = "الفقرة الأولى\r\nالفقرة الثانية\rالفقرة الثالثة";
    $expected = "الفقرة الأولى\nالفقرة الثانية\nالفقرة الثالثة";

    expect($this->normalizer->normalizeWhitespace($input))->toBe($expected);
});

it('replaces non-breaking and thin spaces with regular spaces', function () {
    // \u{00A0} is non-breaking space, \u{2003} is em-space
    $input = "المادة\u{00A0}الثانية:\u{2003}شروط التوظيف";
    $expected = 'المادة الثانية: شروط التوظيف';

    expect($this->normalizer->normalizeWhitespace($input))->toBe($expected);
});

it('collapses excessive blank lines to at most two newlines', function () {
    $input = "الصفحة الأولى\n\n\n\n\nالصفحة الثانية";
    $expected = "الصفحة الأولى\n\nالصفحة الثانية";

    expect($this->normalizer->normalizeWhitespace($input))->toBe($expected);
});

it('runs full normalization covering both numerals and whitespace', function () {
    $input = "  عقد عمل رقم:   ١٢٣٤٥/٢٠٢٦ \r\n\r\n\r\n  قيمة المكافأة: \t ٥٠٠٠ ريال.  ";
    $expected = "عقد عمل رقم: 12345/2026\n\nقيمة المكافأة: 5000 ريال.";

    expect($this->normalizer->normalize($input))->toBe($expected);
});
