<?php

use App\Enums\DocumentStatus;
use App\Enums\ExtractionMethod;
use App\Jobs\Pipeline\ExtractText;
use App\Models\Document;
use App\Services\Extraction\ExtractedPage;
use App\Services\Extraction\OcrExtractor;
use App\Services\Extraction\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// Helper to create a minimal PDF
function createTestPdfWithPages(int $pageCount): string
{
    $out = "%PDF-1.4\n";
    $kids = [];
    $objects = [];

    for ($i = 1; $i <= $pageCount; $i++) {
        $pageObjNum = 2 + $i;
        $kids[] = "{$pageObjNum} 0 R";
        $objects[] = "{$pageObjNum} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
    }

    $kidsStr = implode(' ', $kids);
    $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $out .= "2 0 obj\n<< /Type /Pages /Kids [{$kidsStr}] /Count {$pageCount} >>\nendobj\n";

    foreach ($objects as $obj) {
        $out .= $obj;
    }

    $out .= "xref\n0 ".(3 + $pageCount)."\n";
    $out .= "trailer\n<< /Size ".(3 + $pageCount)." /Root 1 0 R >>\nstartxref\n500\n%%EOF";

    return $out;
}

describe('ExtractText pipeline job', function () {
    it('sets status to extracting and saves cleaned, normalized, masked pages to document_pages', function () {
        $pdfContent = createTestPdfWithPages(2);
        $storageKey = 'documents/clean-contract.pdf';
        Storage::disk('s3')->put($storageKey, $pdfContent);

        $document = Document::create([
            'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'storage_key' => $storageKey,
            'page_count' => 2,
            'status' => DocumentStatus::SCANNING,
            'expires_at' => now()->addHours(24),
        ]);

        $mockPdfExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockPdfExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1)
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 1,
                text: "عقد عمل محدد المدة\nالطرف الأول: شركة الأمل\nالراتب: 8000 ريال",
                charCount: 50,
                extractionMethod: ExtractionMethod::TEXT_LAYER,
                needsOcr: false,
            ));

        $mockPdfExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 2)
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 2,
                text: "الطرف الثاني: سارة أحمد\nرقم الهوية: [NATIONAL_ID]\nفترة التجربة: 90 يوم",
                charCount: 60,
                extractionMethod: ExtractionMethod::TEXT_LAYER,
                needsOcr: false,
            ));

        $realPdfExtractor = new PdfTextExtractor;
        $mockPdfExtractor->shouldReceive('saveAllPages')
            ->once()
            ->andReturnUsing(function ($doc, $pages) use ($realPdfExtractor) {
                return $realPdfExtractor->saveAllPages($doc, $pages);
            });

        $job = new ExtractText($document);
        $job->handle(pdfExtractor: $mockPdfExtractor);

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::EXTRACTING)
            ->and($document->status_detail)->toBeNull()
            ->and($document->pages)->toHaveCount(2);

        $page1 = $document->pages()->where('page_number', 1)->first();
        expect($page1)->not->toBeNull()
            ->and($page1->extraction_method)->toBe(ExtractionMethod::TEXT_LAYER)
            ->and($page1->text)->toContain('عقد عمل محدد المدة')
            ->and($page1->text)->toContain('8000');

        $page2 = $document->pages()->where('page_number', 2)->first();
        expect($page2)->not->toBeNull()
            ->and($page2->extraction_method)->toBe(ExtractionMethod::TEXT_LAYER)
            ->and($page2->text)->toContain('[NATIONAL_ID]');
    });

    it('falls back to OCR for scanned pages and sets is_scanned to true', function () {
        $pdfContent = createTestPdfWithPages(1);
        $storageKey = 'documents/scanned-contract.pdf';
        Storage::disk('s3')->put($storageKey, $pdfContent);

        $document = Document::create([
            'original_filename' => 'scanned.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'storage_key' => $storageKey,
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'is_scanned' => false,
            'expires_at' => now()->addHours(24),
        ]);

        $mockPdfExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockPdfExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1)
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 1,
                text: '',
                charCount: 0,
                extractionMethod: null,
                needsOcr: true,
            ));

        $mockOcrExtractor = Mockery::mock(OcrExtractor::class);
        $mockOcrExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1, Mockery::any())
            ->once()
            ->andReturnUsing(function ($path, $page, $doc) {
                $doc->update(['is_scanned' => true]);

                return new ExtractedPage(
                    pageNumber: 1,
                    text: 'نص مستخرج عبر التعرف الضوئي على الحروف من صفحة ممسوحة ضوئيا',
                    charCount: 57,
                    extractionMethod: ExtractionMethod::OCR,
                    needsOcr: false,
                );
            });

        $realPdfExtractor = new PdfTextExtractor;
        $mockPdfExtractor->shouldReceive('saveAllPages')
            ->once()
            ->andReturnUsing(function ($doc, $pages) use ($realPdfExtractor) {
                return $realPdfExtractor->saveAllPages($doc, $pages);
            });

        $job = new ExtractText($document);
        $job->handle(pdfExtractor: $mockPdfExtractor, ocrExtractor: $mockOcrExtractor);

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::EXTRACTING)
            ->and($document->status_detail)->toBeNull()
            ->and($document->is_scanned)->toBeTrue()
            ->and($document->pages)->toHaveCount(1);

        $page = $document->pages()->first();
        expect($page->extraction_method)->toBe(ExtractionMethod::OCR)
            ->and($page->text)->toContain('ممسوحة ضوئيا');
    });

    it('pauses processing with ai_budget_reached and does not mark failed when AI budget is exhausted', function () {
        $pdfContent = createTestPdfWithPages(1);
        $storageKey = 'documents/budget-test.pdf';
        Storage::disk('s3')->put($storageKey, $pdfContent);

        $document = Document::create([
            'original_filename' => 'budget.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'storage_key' => $storageKey,
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'expires_at' => now()->addHours(24),
        ]);

        $mockPdfExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockPdfExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1)
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 1,
                text: '',
                charCount: 0,
                extractionMethod: null,
                needsOcr: true,
            ));

        // Mock OcrExtractor to simulate budget guard pause
        $mockOcrExtractor = Mockery::mock(OcrExtractor::class);
        $mockOcrExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1, Mockery::any())
            ->once()
            ->andReturnUsing(function ($path, $page, $doc) {
                $doc->update(['status_detail' => 'ai_budget_reached']);

                return new ExtractedPage(
                    pageNumber: 1,
                    text: '',
                    charCount: 0,
                    extractionMethod: null,
                    needsOcr: true,
                );
            });

        // saveAllPages must NOT be called when paused
        $mockPdfExtractor->shouldNotReceive('saveAllPages');

        $job = new ExtractText($document);
        $job->handle(pdfExtractor: $mockPdfExtractor, ocrExtractor: $mockOcrExtractor);

        $document->refresh();

        // Must remain extracting, paused with ai_budget_reached, NOT failed
        expect($document->status)->toBe(DocumentStatus::EXTRACTING)
            ->and($document->status_detail)->toBe('ai_budget_reached')
            ->and($document->status)->not->toBe(DocumentStatus::FAILED);
    });

    it('marks document as failed with file_unreadable when all pages yield no readable text', function () {
        $pdfContent = createTestPdfWithPages(1);
        $storageKey = 'documents/blank.pdf';
        Storage::disk('s3')->put($storageKey, $pdfContent);

        $document = Document::create([
            'original_filename' => 'blank.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'storage_key' => $storageKey,
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'expires_at' => now()->addHours(24),
        ]);

        $mockPdfExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockPdfExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1)
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 1,
                text: '',
                charCount: 0,
                extractionMethod: null,
                needsOcr: true,
            ));

        $mockOcrExtractor = Mockery::mock(OcrExtractor::class);
        $mockOcrExtractor->shouldReceive('extractPage')
            ->with(Mockery::any(), 1, Mockery::any())
            ->once()
            ->andReturn(new ExtractedPage(
                pageNumber: 1,
                text: '',
                charCount: 0,
                extractionMethod: null,
                needsOcr: true,
            ));

        $mockPdfExtractor->shouldNotReceive('saveAllPages');

        $job = new ExtractText($document);
        $job->handle(pdfExtractor: $mockPdfExtractor, ocrExtractor: $mockOcrExtractor);

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::FAILED)
            ->and($document->status_detail)->toBe('file_unreadable')
            ->and($document->pages)->toHaveCount(0);
    });

    it('marks document as failed with file_unreadable when file is missing from storage', function () {
        $document = Document::create([
            'original_filename' => 'missing.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => hash('sha256', 'missing'),
            'storage_key' => 'documents/does-not-exist.pdf',
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'expires_at' => now()->addHours(24),
        ]);

        $job = new ExtractText($document);
        $job->handle();

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::FAILED)
            ->and($document->status_detail)->toBe('file_unreadable');
    });

    it('marks document as failed with file_unreadable when an unexpected exception occurs during extraction', function () {
        $pdfContent = createTestPdfWithPages(1);
        $storageKey = 'documents/corrupt.pdf';
        Storage::disk('s3')->put($storageKey, $pdfContent);

        $document = Document::create([
            'original_filename' => 'corrupt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'storage_key' => $storageKey,
            'page_count' => 1,
            'status' => DocumentStatus::SCANNING,
            'expires_at' => now()->addHours(24),
        ]);

        $mockPdfExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockPdfExtractor->shouldReceive('extractPage')
            ->andThrow(new RuntimeException('Corrupted PDF syntax'));

        $job = new ExtractText($document);
        $job->handle(pdfExtractor: $mockPdfExtractor);

        $document->refresh();

        expect($document->status)->toBe(DocumentStatus::FAILED)
            ->and($document->status_detail)->toBe('file_unreadable');
    });
});
