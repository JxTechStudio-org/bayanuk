<?php

use App\Services\Extraction\PiiMasker;
use App\Services\Extraction\TextNormalizer;

beforeEach(function () {
    $this->masker = new PiiMasker(new TextNormalizer);
});

describe('Saudi National ID and Iqama masking', function () {
    it('masks 10-digit Saudi national ID starting with 1', function () {
        $text = 'رقم السجل المدني للطرف الثاني: 1087654321 مسجل في الرياض.';
        expect($this->masker->mask($text))->toBe('رقم السجل المدني للطرف الثاني: [NATIONAL_ID] مسجل في الرياض.');
    });

    it('masks formatted Saudi national ID with dashes or spaces', function () {
        $text = 'الهوية الوطنية: 1-08765432-1 أو 1087 654 321.';
        expect($this->masker->mask($text))->toBe('الهوية الوطنية: [NATIONAL_ID] أو [NATIONAL_ID].');
    });

    it('masks Saudi national ID written in Eastern Arabic numerals', function () {
        $text = 'الهوية الوطنية: ١٠٨٧٦٥٤٣٢١.';
        expect($this->masker->mask($text))->toBe('الهوية الوطنية: [NATIONAL_ID].');
    });

    it('masks 10-digit Saudi resident Iqama starting with 2', function () {
        $text = 'رقم الإقامة للموظف: 2098765432 الصادرة من جوازات جدة.';
        expect($this->masker->mask($text))->toBe('رقم الإقامة للموظف: [IQAMA] الصادرة من جوازات جدة.');
    });

    it('masks formatted Iqama with dashes or spaces', function () {
        $text = 'Iqama Number: 2-09876543-2 or 2098 765 432.';
        expect($this->masker->mask($text))->toBe('Iqama Number: [IQAMA] or [IQAMA].');
    });

    it('masks Iqama written in Eastern Arabic numerals', function () {
        $text = 'رقم الإقامة: ٢٠٩٨٧٦٥٤٣٢.';
        expect($this->masker->mask($text))->toBe('رقم الإقامة: [IQAMA].');
    });
});

describe('Email address masking', function () {
    it('masks standard and corporate email addresses', function () {
        $text = 'للتواصل عبر البريد: hr@company.com.sa أو mohammed.ali@domain.org.';
        expect($this->masker->mask($text))->toBe('للتواصل عبر البريد: [EMAIL] أو [EMAIL].');
    });

    it('does not corrupt emails that contain ID numbers or phone numbers inside them', function () {
        $text = 'User email: employee1087654321@test.com and candidate0501234567@corp.com';
        expect($this->masker->mask($text))->toBe('User email: [EMAIL] and [EMAIL]');
    });
});

describe('IBAN masking', function () {
    it('masks continuous Saudi IBAN', function () {
        $text = 'الراتب يحول إلى الحساب البنكي رقم SA0380000000608010167519 في بنك الراجحي.';
        expect($this->masker->mask($text))->toBe('الراتب يحول إلى الحساب البنكي رقم [IBAN] في بنك الراجحي.');
    });

    it('masks Saudi IBAN separated by spaces', function () {
        $text = 'IBAN: SA03 8000 0000 6080 1016 7519 at Alinma Bank.';
        expect($this->masker->mask($text))->toBe('IBAN: [IBAN] at Alinma Bank.');
    });

    it('masks International IBAN', function () {
        $text = 'UAE Account: AE070331234567890123456 and UK: GB29NWBK60161331926819.';
        expect($this->masker->mask($text))->toBe('UAE Account: [IBAN] and UK: [IBAN].');
    });

    it('masks reversed Saudi IBAN with SA03 at the end resulting from RTL OCR reading', function () {
        $text = 'رقم الآيبان: 7519 1016 6080 0000 8000 SA03 لدى البنك الأهلي.';
        expect($this->masker->mask($text))->toBe('رقم الآيبان: [IBAN] لدى البنك الأهلي.');
    });

    it('masks reversed Saudi IBAN with leading dot before SA03 as seen in RTL OCR sentence end', function () {
        $text = 'ويحول الراتب إلى الحساب البنكي رقم الآيبان 7519 1016 6080 0000 8000 .SA03';
        expect($this->masker->mask($text))->toBe('ويحول الراتب إلى الحساب البنكي رقم الآيبان [IBAN]');
    });

    it('masks reversed Saudi IBAN with 03SA or 30AS at the end', function () {
        $text = 'رقم الآيبان: 7519 1016 6080 0000 8000 03SA والآخر 7519 1016 6080 0000 8000 30AS.';
        expect($this->masker->mask($text))->toBe('رقم الآيبان: [IBAN] والآخر [IBAN].');
    });

    it('masks continuous reversed Saudi IBAN', function () {
        $text = 'IBAN: 75191016608000008000SA03 or 7519101660800000800003SA.';
        expect($this->masker->mask($text))->toBe('IBAN: [IBAN] or [IBAN].');
    });
});

describe('Phone number masking', function () {
    it('masks Saudi local mobile numbers (05xxxxxxxx)', function () {
        $text = 'رقم جوال الموظف: 0501234567 للتواصل السريع.';
        expect($this->masker->mask($text))->toBe('رقم جوال الموظف: [PHONE] للتواصل السريع.');
    });

    it('masks Saudi international mobile numbers (+966, 00966, 966)', function () {
        $text = 'الاتصال على +966501234567 أو 00966551234567 أو 966591234567.';
        expect($this->masker->mask($text))->toBe('الاتصال على [PHONE] أو [PHONE] أو [PHONE].');
    });

    it('masks Saudi mobile numbers with spaces or hyphens', function () {
        $text = 'Mobile: +966 50 123 4567 or 050-123-4567.';
        expect($this->masker->mask($text))->toBe('Mobile: [PHONE] or [PHONE].');
    });

    it('masks Saudi landline phone numbers', function () {
        $text = 'هاتف المكتب في الرياض: 0112345678 أو +966 11 234 5678.';
        expect($this->masker->mask($text))->toBe('هاتف المكتب في الرياض: [PHONE] أو [PHONE].');
    });

    it('masks phone numbers written in Eastern Arabic numerals', function () {
        $text = 'جوال الموظف: ٠٥٥١٢٣٤٥٦٧.';
        expect($this->masker->mask($text))->toBe('جوال الموظف: [PHONE].');
    });

    it('masks general international phone numbers', function () {
        $text = 'International support: +1 555 234 5678 or +44 20 7946 0919.';
        expect($this->masker->mask($text))->toBe('International support: [PHONE] or [PHONE].');
    });
});

describe('Preservation of non-PII numbers and contract data', function () {
    it('preserves salaries, bonuses, and allowance numbers', function () {
        $text = 'الراتب الأساسي 12000 ريال، وبدل السكن 3000 ريال، وبدل النقل 1000 ريال.';
        expect($this->masker->mask($text))->toBe($text);
    });

    it('preserves contract clause numbers and day counts', function () {
        $text = 'المادة 14: فترة التجربة 90 يوماً وتمدد إلى 180 يوماً باتفاق الطرفين.';
        expect($this->masker->mask($text))->toBe($text);
    });

    it('preserves dates and years', function () {
        $text = 'يبدأ العقد بتاريخ 2026-11-01 وينتهي في 2027-10-31 ميلادي.';
        expect($this->masker->mask($text))->toBe($text);
    });
});

describe('Realistic full contract samples', function () {
    it('masks all personal identifiers in an Arabic contract clause', function () {
        $clause = 'الطرف الثاني: سارة خالد، رقم الهوية الوطنية: ١٠٨٧٦٥٤٣٢١، جوال: 0551234567، بريد: sarah@example.com، حساب آيبان: SA0380000000608010167519، الراتب الأساسي: 9500 ريال.';
        $masked = $this->masker->mask($clause);

        expect($masked)->toContain('[NATIONAL_ID]')
            ->toContain('[PHONE]')
            ->toContain('[EMAIL]')
            ->toContain('[IBAN]')
            ->toContain('الراتب الأساسي: 9500 ريال')
            ->not->toContain('1087654321')
            ->not->toContain('0551234567')
            ->not->toContain('sarah@example.com')
            ->not->toContain('SA0380000000608010167519');
    });

    it('masks all personal identifiers in an English job offer clause', function () {
        $clause = 'Candidate: John Smith, Resident ID: 2098765432, Contact: +966 50 987 6543, Email: j.smith@enterprise.sa, Bank Account: SA4410000000123456789012, Offer Basic: 18000 SAR.';
        $masked = $this->masker->mask($clause);

        expect($masked)->toContain('[IQAMA]')
            ->toContain('[PHONE]')
            ->toContain('[EMAIL]')
            ->toContain('[IBAN]')
            ->toContain('Offer Basic: 18000 SAR')
            ->not->toContain('2098765432')
            ->not->toContain('+966 50 987 6543')
            ->not->toContain('j.smith@enterprise.sa')
            ->not->toContain('SA4410000000123456789012');
    });
});
