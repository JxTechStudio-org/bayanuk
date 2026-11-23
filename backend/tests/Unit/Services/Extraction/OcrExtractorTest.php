<?php

use App\Enums\ExtractionMethod;
use App\Models\Document;
use App\Services\AI\AIService;
use App\Services\AI\BudgetGuard;
use App\Services\Extraction\ArabicTextChecker;
use App\Services\Extraction\ExtractedPage;
use App\Services\Extraction\OcrExtractor;
use App\Services\Extraction\PiiMasker;
use App\Services\Extraction\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Tier 1: Tesseract OCR execution', function () {
    it('uses Tesseract OCR when it extracts valid readable Arabic and English text', function () {
        $document = Document::factory()->create(['is_scanned' => false]);

        $ocrExtractor = new class(new ArabicTextChecker, new TextNormalizer, new PiiMasker, new BudgetGuard) extends OcrExtractor
        {
            protected function renderPageToImage(string $pdfPath, int $page): ?string
            {
                $tmp = tempnam(sys_get_temp_dir(), 'test_page_');
                file_put_contents($tmp, 'fake-image-content');

                return $tmp;
            }

            protected function runTesseract(string $imagePath): string
            {
                return "عقد عمل موثق\nالطرف الثاني: محمد خالد، هوية: ١٠٨٧٦٥٤٣٢١، جوال: 0551234567، الراتب: ٨٠٠٠ ريال.\f";
            }
        };

        $result = $ocrExtractor->extractPage('/fake/file.pdf', 1, $document);

        expect($result)->toBeInstanceOf(ExtractedPage::class)
            ->and($result->needsOcr)->toBeFalse()
            ->and($result->extractionMethod)->toBe(ExtractionMethod::OCR)
            ->and($result->text)->toContain('عقد عمل موثق')
            ->and($result->text)->toContain('[NATIONAL_ID]')
            ->and($result->text)->toContain('[PHONE]')
            ->and($result->text)->toContain('الراتب: 8000 ريال')
            ->and($result->text)->not->toContain('1087654321')
            ->and($result->text)->not->toContain('0551234567');

        $document->refresh();
        expect($document->is_scanned)->toBeTrue();
    });
});

describe('Tier 2: AI Budget Guard before Gemini Vision OCR fallback', function () {
    it('pauses document with status_detail ai_budget_reached when daily budget is reached and Tesseract fails', function () {
        $document = Document::factory()->create([
            'status' => 'extracting',
            'status_detail' => null,
            'is_scanned' => false,
        ]);

        $budgetGuardMock = Mockery::mock(BudgetGuard::class);
        $budgetGuardMock->shouldReceive('isBudgetReached')->once()->andReturnTrue();

        $aiServiceMock = Mockery::mock(AIService::class);
        $aiServiceMock->shouldNotReceive('ocrFallback');

        $ocrExtractor = new class(new ArabicTextChecker, new TextNormalizer, new PiiMasker, $budgetGuardMock, $aiServiceMock) extends OcrExtractor
        {
            protected function renderPageToImage(string $pdfPath, int $page): ?string
            {
                $tmp = tempnam(sys_get_temp_dir(), 'test_page_');
                file_put_contents($tmp, 'fake-image-content');

                return $tmp;
            }

            protected function runTesseract(string $imagePath): string
            {
                // Tesseract fails with empty / unreadable text
                return "\f   \n";
            }
        };

        $result = $ocrExtractor->extractPage('/fake/unreadable.pdf', 1, $document);

        expect($result->needsOcr)->toBeTrue()
            ->and($result->extractionMethod)->toBeNull();

        $document->refresh();
        expect($document->status_detail)->toBe('ai_budget_reached');
    });

    it('invokes Gemini Vision OCR fallback when budget is available and Tesseract fails', function () {
        $document = Document::factory()->create([
            'status' => 'extracting',
            'status_detail' => null,
            'is_scanned' => false,
        ]);

        $budgetGuardMock = Mockery::mock(BudgetGuard::class);
        $budgetGuardMock->shouldReceive('isBudgetReached')->once()->andReturnFalse();

        $aiServiceMock = Mockery::mock(AIService::class);
        $aiServiceMock->shouldReceive('ocrFallback')
            ->once()
            ->andReturn("Standard Employment Contract\nSecond Party: John Doe, Iqama: 2098765432, Basic: 12000 SAR.");

        $ocrExtractor = new class(new ArabicTextChecker, new TextNormalizer, new PiiMasker, $budgetGuardMock, $aiServiceMock) extends OcrExtractor
        {
            protected function renderPageToImage(string $pdfPath, int $page): ?string
            {
                $tmp = tempnam(sys_get_temp_dir(), 'test_page_');
                file_put_contents($tmp, 'fake-image-content');

                return $tmp;
            }

            protected function runTesseract(string $imagePath): string
            {
                return ''; // Tesseract fails
            }
        };

        $result = $ocrExtractor->extractPage('/fake/scanned_vision.pdf', 1, $document);

        expect($result->needsOcr)->toBeFalse()
            ->and($result->extractionMethod)->toBe(ExtractionMethod::VISION)
            ->and($result->text)->toContain('[IQAMA]')
            ->and($result->text)->toContain('Basic: 12000 SAR')
            ->and($result->text)->not->toContain('2098765432');

        $document->refresh();
        expect($document->is_scanned)->toBeTrue()
            ->and($document->status_detail)->toBeNull();
    });

    it('marks page as failed when both Tesseract and Gemini Vision fail', function () {
        $document = Document::factory()->create();

        $budgetGuardMock = Mockery::mock(BudgetGuard::class);
        $budgetGuardMock->shouldReceive('isBudgetReached')->andReturnFalse();

        $aiServiceMock = Mockery::mock(AIService::class);
        $aiServiceMock->shouldReceive('ocrFallback')->andReturn('');

        $ocrExtractor = new class(new ArabicTextChecker, new TextNormalizer, new PiiMasker, $budgetGuardMock, $aiServiceMock) extends OcrExtractor
        {
            protected function renderPageToImage(string $pdfPath, int $page): ?string
            {
                $tmp = tempnam(sys_get_temp_dir(), 'test_page_');
                file_put_contents($tmp, 'fake-image-content');

                return $tmp;
            }

            protected function runTesseract(string $imagePath): string
            {
                return '';
            }
        };

        $result = $ocrExtractor->extractPage('/fake/corrupted.pdf', 1, $document);

        expect($result->needsOcr)->toBeTrue()
            ->and($result->extractionMethod)->toBeNull();
    });
});
