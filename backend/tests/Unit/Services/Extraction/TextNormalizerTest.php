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

it('strips invisible Unicode directional marks and zero-width characters', function () {
    // \u{202B} is RLE, \u{202C} is PDF, \u{202A} is LRE, \u{200E} is LTR mark, \u{200F} is RTL mark
    $input = "\u{202B}عقد\u{200E} عمل\u{200F} محدد\u{202C} المدة\u{FEFF}\u{202A}";
    $expected = 'عقد عمل محدد المدة';

    expect($this->normalizer->stripInvisibleFormatting($input))->toBe($expected)
        ->and($this->normalizer->normalize($input))->toBe($expected);
});

it('normalizes broken Arabic ligatures caused by reversed glyphs in PDF extraction', function () {
    $input = 'الطرف األول يلتزم بعدم التعديل إال بموافقة كتابية من الطرف الثاني خالل ثالثين يوماً من تاريخ اإلشعار ويستحق األجر.';
    $expected = 'الطرف الأول يلتزم بعدم التعديل إلا بموافقة كتابية من الطرف الثاني خلال ثلاثين يوماً من تاريخ الإشعار ويستحق الأجر.';

    expect($this->normalizer->normalizeArabicLigatures($input))->toBe($expected);
});

it('normalizes the contract snippet from manager review screenshot', function () {
    $input = "\u{202B}أبرم هذا العقد بين شركة األفق للتقنية (الطرف األول) والسيدة سارة محمد (الطرف\u{202C}\n"
        ."\u{202B}يلتزم الطرف األول بدفع راتب أساسي قدره \u{202A}12000\u{202C}ريال شهريا\u{202A}, \u{202C}\u{202A},\u{202C} وبدل سكن \u{202A}3000\u{202C}ريال\u{202A},\u{202C}\n"
        ."\u{202B}تمديدها إال باتفاق مكتوب بين الطرفين وفقا للمادة \u{202A}53\u{202C}من نظام العمل\u{202A}.\u{202C}\u{202C}";

    $normalized = $this->normalizer->normalize($input);

    expect($normalized)->not->toContain("\u{202B}")
        ->and($normalized)->not->toContain("\u{202C}")
        ->and($normalized)->not->toContain("\u{202A}")
        ->and($normalized)->toContain('شركة الأفق للتقنية')
        ->and($normalized)->toContain('(الطرف الأول)')
        ->and($normalized)->toContain('تمديدها إلا باتفاق');
});
