<?php

use App\Enums\DocumentStatus;
use App\Enums\ExtractionMethod;
use App\Jobs\Pipeline\ExtractText;
use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

describe('Definition of Done (DoD) - Real Contract Fixtures', function () {
    it('satisfies DoD for a real Arabic text PDF with ligatures, directional markers, numerals, and PII masking', function () {
        $fixturePath = base_path('tests/Fixtures/sample_contract_arabic.pdf');
        expect(file_exists($fixturePath))->toBeTrue();

        $pdfBinary = file_get_contents($fixturePath);
        $storageKey = 'documents/real-arabic-contract.pdf';
        Storage::disk('s3')->put($storageKey, $pdfBinary);

        $document = Document::create([
            'guest_token_hash' => str_repeat('a', 64),
            'original_filename' => 'عقد_عمل_عربي.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfBinary),
            'sha256' => hash('sha256', $pdfBinary),
            'storage_key' => $storageKey,
            'page_count' => 2,
            'status' => DocumentStatus::SCANNING,
            'status_detail' => null,
            'expires_at' => now()->addHours(24),
        ]);

        // Run ExtractText with real Poppler extractor and real text normalizer/pii masker
        $job = new ExtractText($document);
        $job->handle();

        $document->refresh();

        // 1. Status transition & scanned flag
        expect($document->status)->toBe(DocumentStatus::EXTRACTING)
            ->and($document->status_detail)->toBeNull()
            ->and($document->is_scanned)->toBeFalse();

        // 2. Document pages created for both pages
        $pages = DocumentPage::query()
            ->where('document_id', $document->id)
            ->orderBy('page_number')
            ->get();

        expect($pages)->toHaveCount(2);

        $page1 = $pages->first();
        expect($page1->page_number)->toBe(1)
            ->and($page1->extraction_method)->toBe(ExtractionMethod::TEXT_LAYER);

        $text1 = $page1->text;

        // 3. Verify hidden directional markers are completely stripped (U+202A, U+202B, U+202C, U+200E, etc.)
        expect(preg_match('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', $text1))->toBe(0);

        // 4. Verify broken Arabic ligatures are normalized (e.g. األول -> الأول, إال -> إلا)
        expect($text1)->not->toContain('األول')
            ->and($text1)->toContain('الأول')
            ->and($text1)->not->toContain('إال بموافقة')
            ->and($text1)->toContain('إلا بموافقة');

        // 5. Verify Arabic numerals are converted to Western numerals
        expect(preg_match('/[٠-٩]/u', $text1))->toBe(0);

        // 6. Verify PII is masked (National ID, Phone, Email, IBAN)
        expect($text1)->toContain('[NATIONAL_ID]')
            ->and($text1)->toContain('[PHONE]')
            ->and($text1)->toContain('[EMAIL]')
            ->and($text1)->toContain('[IBAN]')
            ->and($text1)->not->toContain('1098765432') // Raw National ID in contract
            ->and($text1)->not->toContain('0501234567') // Raw Phone in contract
            ->and($text1)->not->toContain('ahmed.ali@example.com') // Raw Email in contract
            ->and($text1)->not->toContain('SA0380000000608010167519'); // Raw IBAN in contract

        $page2 = $pages->last();
        expect($page2->page_number)->toBe(2)
            ->and($page2->extraction_method)->toBe(ExtractionMethod::TEXT_LAYER);
    });

    it('satisfies DoD for a real scanned PDF by falling back to local Tesseract OCR', function () {
        $fixturePath = base_path('tests/Fixtures/sample_contract_scanned.pdf');
        expect(file_exists($fixturePath))->toBeTrue();

        $pdfBinary = file_get_contents($fixturePath);
        $storageKey = 'documents/real-scanned-contract.pdf';
        Storage::disk('s3')->put($storageKey, $pdfBinary);

        $document = Document::create([
            'guest_token_hash' => str_repeat('b', 64),
            'original_filename' => 'عقد_ممسوح.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfBinary),
            'sha256' => hash('sha256', $pdfBinary),
            'storage_key' => $storageKey,
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'status_detail' => null,
            'expires_at' => now()->addHours(24),
        ]);

        // Zero AI tokens: runs entirely on local Poppler + Tesseract OCR
        $job = new ExtractText($document);
        $job->handle();

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::EXTRACTING)
            ->and($document->status_detail)->toBeNull()
            ->and($document->is_scanned)->toBeTrue();

        $pages = DocumentPage::query()
            ->where('document_id', $document->id)
            ->get();

        expect($pages)->toHaveCount(1);

        $page = $pages->first();
        expect($page->page_number)->toBe(1)
            ->and($page->extraction_method)->toBe(ExtractionMethod::OCR);

        $text = $page->text;

        // Scanned page has substantial text extracted via OCR (> 500 characters)
        expect(mb_strlen($text))->toBeGreaterThan(500);

        // Verify Arabic text was read by OCR
        expect($text)->toMatch('/[\p{Arabic}]/u');

        // Verify PII is masked even in OCR (including reversed Saudi IBANs)
        expect($text)->not->toContain('SA0380000000608010167519')
            ->and($text)->not->toContain('0380000000608010167519SA')
            ->and($text)->not->toContain('1098765432');
    });

    it('satisfies DoD file type validation by rejecting DOCX uploads with 422', function () {
        $fakeDocx = UploadedFile::fake()->create('contract.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->withHeader('X-Guest-Token', str_repeat('c', 64))
            ->postJson('/api/v1/documents', [
                'file' => $fakeDocx,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'file_type_not_supported');
    });
});
