<?php

use App\Services\Extraction\ArabicTextChecker;

beforeEach(function () {
    $this->checker = new ArabicTextChecker;
});

describe('Valid and normal contract texts', function () {
    it('recognizes valid Arabic employment contract text as not needing OCR', function () {
        $text = 'عقد عمل محدد المدة، تم الاتفاق بين الطرف الأول شركة بيانك لتقنية المعلومات والطرف الثاني الموظف على الشروط والبنود التالية براتب أساسي قدره 10000 ريال سعودي.';

        expect($this->checker->hasSufficientText($text))->toBeTrue()
            ->and($this->checker->isGarbled($text))->toBeFalse()
            ->and($this->checker->isReversed($text))->toBeFalse()
            ->and($this->checker->isDisconnected($text))->toBeFalse()
            ->and($this->checker->needsOcr($text))->toBeFalse();
    });

    it('recognizes valid English employment agreement as not needing OCR', function () {
        $text = 'This Employment Agreement is made and entered into on this day by and between Bayanuk Tech Corp and the Employee, setting forth terms of employment and compensation.';

        expect($this->checker->hasSufficientText($text))->toBeTrue()
            ->and($this->checker->isGarbled($text))->toBeFalse()
            ->and($this->checker->needsOcr($text))->toBeFalse();
    });

    it('recognizes valid bilingual Arabic and English contract text', function () {
        $text = 'عقد عمل موحد / Standard Employment Agreement. الطرف الأول / First Party: شركة التقنية. الراتب الأساسي: 12000 SAR basic monthly salary.';

        expect($this->checker->hasSufficientText($text))->toBeTrue()
            ->and($this->checker->isGarbled($text))->toBeFalse()
            ->and($this->checker->needsOcr($text))->toBeFalse();
    });
});

describe('Reversed Arabic text detection', function () {
    it('detects reversed Arabic text extracted with LTR ordering', function () {
        // Reversed form of: المملكة العربية السعودية شركة بيانك عقد عمل الطرف الأول
        $reversedText = 'ةكلمملا ةيدوعسلا ةكرش كنايب دقع لمع فرطلا لولأا بتارلا يساملأا 00001 لاير';

        expect($this->checker->hasArabicContent($reversedText))->toBeTrue()
            ->and($this->checker->isReversed($reversedText))->toBeTrue()
            ->and($this->checker->isGarbled($reversedText))->toBeTrue()
            ->and($this->checker->needsOcr($reversedText))->toBeTrue();
    });

    it('detects reversed text containing multiple words starting with Taa Marbuta', function () {
        // In Arabic, ة cannot start a word unless reversed from end of word (e.g. ةكرش, ةكلمملا, ةبرجتلا)
        $text = 'مت عيقوت ةيقافتلا يف ةنيدم ضايرلا نيب ةكرش ىلوأو فرطلا يناثلا عم ةبرجتلا ةدمب 09 اموي.';

        expect($this->checker->isReversed($text))->toBeTrue()
            ->and($this->checker->needsOcr($text))->toBeTrue();
    });
});

describe('Disconnected and broken Arabic text detection', function () {
    it('detects broken Arabic letters separated by spaces', function () {
        // Each character printed as a single token: ع ق د   ع م ل   م ح د د   ا ل م د ة
        $brokenText = 'ع ق د   ع م ل   م ح د د   ا ل م د ة   ب ي ن   ش ر ك ة   ب ي ا ن ك   و ا ل م و ظ ف   أ ح م د';

        expect($this->checker->isDisconnected($brokenText))->toBeTrue()
            ->and($this->checker->isGarbled($brokenText))->toBeTrue()
            ->and($this->checker->needsOcr($brokenText))->toBeTrue();
    });

    it('detects high density of Arabic presentation forms', function () {
        // Presentation Forms-B characters (\u{FE8E}, \u{FEAA}, etc.)
        $presentationFormText = "\u{FE8E}\u{FEAA}\u{FEDF}\u{FE8F} \u{FEE7}\u{FE91}\u{FEA3} \u{FEE3}\u{FEAE}\u{FE8D}\u{FEE0}\u{FE8E} \u{FE97}\u{FE8E}\u{FECC}\u{FEE0}\u{FE8E} \u{FE9F}\u{FEAA}\u{FEDF}\u{FE8E}";

        expect($this->checker->isDisconnected($presentationFormText))->toBeTrue()
            ->and($this->checker->needsOcr($presentationFormText))->toBeTrue();
    });
});

describe('Scanned and insufficient text detection', function () {
    it('marks empty or whitespace-only pages as needing OCR', function () {
        expect($this->checker->needsOcr(''))->toBeTrue()
            ->and($this->checker->needsOcr("   \n\t  \r\n   "))->toBeTrue()
            ->and($this->checker->hasSufficientText(''))->toBeFalse();
    });

    it('marks scanned pages with only a page number or artifact symbols as needing OCR', function () {
        // Typical pdftotext output on a scanned page: just formfeed or page number
        $scannedOutput = "\f  \n - 1 - \n  ";

        expect($this->checker->hasSufficientText($scannedOutput))->toBeFalse()
            ->and($this->checker->needsOcr($scannedOutput))->toBeTrue();
    });

    it('honors custom minChars parameter', function () {
        $shortText = 'Short notice text only.';

        expect($this->checker->hasSufficientText($shortText, 50))->toBeFalse()
            ->and($this->checker->hasSufficientText($shortText, 10))->toBeTrue();
    });
});
