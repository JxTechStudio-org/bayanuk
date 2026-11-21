<?php

use App\Enums\ExtractionMethod;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Services\Extraction\ArabicTextChecker;
use App\Services\Extraction\ExtractedPage;
use App\Services\Extraction\PageCounter;
use App\Services\Extraction\PdfTextExtractor;
use App\Services\Extraction\PiiMasker;
use App\Services\Extraction\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->extractor = new PdfTextExtractor(
        new PageCounter,
        new ArabicTextChecker,
        new TextNormalizer,
        new PiiMasker
    );
});

describe('Page text extraction and processing', function () {
    it('cleans text, normalizes Arabic numerals, and masks PII from extracted text', function () {
        // Subclass to mock raw pdftotext output
        $extractor = new class(new PageCounter, new ArabicTextChecker, new TextNormalizer, new PiiMasker) extends PdfTextExtractor
        {
            protected function runPdftotext(string $pdfPath, int $page): string
            {
                return "عقد عمل محدد المدة\nالطرف الثاني: سارة علي، سجل مدني: ١٠٨٧٦٥٤٣٢١، جوال: 0551234567، بريد: sarah@example.com، الراتب: ٩٥٠٠ ريال.\f";
            }
        };

        $result = $extractor->extractPage('/fake/path.pdf', 1);

        expect($result)->toBeInstanceOf(ExtractedPage::class)
            ->and($result->pageNumber)->toBe(1)
            ->and($result->needsOcr)->toBeFalse()
            ->and($result->extractionMethod)->toBe(ExtractionMethod::TEXT_LAYER)
            ->and($result->text)->toContain('عقد عمل محدد المدة')
            ->and($result->text)->toContain('[NATIONAL_ID]')
            ->and($result->text)->toContain('[PHONE]')
            ->and($result->text)->toContain('[EMAIL]')
            ->and($result->text)->toContain('الراتب: 9500 ريال')
            ->and($result->text)->not->toContain('1087654321')
            ->and($result->text)->not->toContain('0551234567')
            ->and($result->text)->not->toContain("\f")
            ->and($result->charCount)->toBe(mb_strlen($result->text));
    });

    it('flags page as needing OCR when pdftotext returns insufficient or empty text', function () {
        $extractor = new class(new PageCounter, new ArabicTextChecker, new TextNormalizer, new PiiMasker) extends PdfTextExtractor
        {
            protected function runPdftotext(string $pdfPath, int $page): string
            {
                return "\f  \n - 1 - \n  ";
            }
        };

        $result = $extractor->extractPage('/fake/scanned.pdf', 1);

        expect($result->needsOcr)->toBeTrue()
            ->and($result->extractionMethod)->toBeNull()
            ->and($result->text)->toBe('')
            ->and($result->charCount)->toBe(0);
    });

    it('flags page as needing OCR when Arabic text is reversed or broken', function () {
        $extractor = new class(new PageCounter, new ArabicTextChecker, new TextNormalizer, new PiiMasker) extends PdfTextExtractor
        {
            protected function runPdftotext(string $pdfPath, int $page): string
            {
                return 'ةكلمملا ةيدوعسلا ةكرش كنايب دقع لمع فرطلا لولأا بتارلا يساملأا 00001 لاير';
            }
        };

        $result = $extractor->extractPage('/fake/reversed.pdf', 1);

        expect($result->needsOcr)->toBeTrue()
            ->and($result->extractionMethod)->toBeNull();
    });

    it('extracts all pages for a multi-page PDF', function () {
        $pageCounterMock = Mockery::mock(PageCounter::class);
        $pageCounterMock->shouldReceive('count')->andReturn(3);

        $extractor = new class($pageCounterMock, new ArabicTextChecker, new TextNormalizer, new PiiMasker) extends PdfTextExtractor
        {
            protected function runPdftotext(string $pdfPath, int $page): string
            {
                return "This is employment agreement content for page {$page} with enough characters to pass text check.";
            }
        };

        $pages = $extractor->extractAll('/fake/multipage.pdf');

        expect($pages)->toHaveCount(3)
            ->and($pages[1]->pageNumber)->toBe(1)
            ->and($pages[2]->pageNumber)->toBe(2)
            ->and($pages[3]->pageNumber)->toBe(3)
            ->and($pages[1]->extractionMethod)->toBe(ExtractionMethod::TEXT_LAYER);
    });
});

describe('Database persistence in document_pages', function () {
    it('persists extracted page into document_pages table', function () {
        $document = Document::factory()->create();

        $extractedPage = new ExtractedPage(
            pageNumber: 1,
            text: 'Cleaned and masked contract content for page 1.',
            charCount: 46,
            extractionMethod: ExtractionMethod::TEXT_LAYER,
            needsOcr: false
        );

        $savedPage = $this->extractor->savePage($document, $extractedPage);

        expect($savedPage)->toBeInstanceOf(DocumentPage::class)
            ->and($savedPage->document_id)->toBe($document->id)
            ->and($savedPage->page_number)->toBe(1)
            ->and($savedPage->text)->toBe('Cleaned and masked contract content for page 1.')
            ->and($savedPage->char_count)->toBe(46)
            ->and($savedPage->extraction_method)->toBe(ExtractionMethod::TEXT_LAYER);

        $this->assertDatabaseHas('document_pages', [
            'document_id' => $document->id,
            'page_number' => 1,
            'char_count' => 46,
            'extraction_method' => 'text_layer',
        ]);
    });

    it('updates existing page on re-run instead of creating duplicates', function () {
        $document = Document::factory()->create();

        $pageV1 = new ExtractedPage(1, 'Original text', 13, ExtractionMethod::TEXT_LAYER);
        $this->extractor->savePage($document, $pageV1);

        $pageV2 = new ExtractedPage(1, 'Updated text after re-extraction', 32, ExtractionMethod::TEXT_LAYER);
        $this->extractor->savePage($document, $pageV2);

        expect($document->pages()->count())->toBe(1);
        $this->assertDatabaseHas('document_pages', [
            'document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Updated text after re-extraction',
        ]);
    });

    it('saves a collection of extracted pages in batch', function () {
        $document = Document::factory()->create();

        $pages = [
            1 => new ExtractedPage(1, 'Page 1 content', 14, ExtractionMethod::TEXT_LAYER),
            2 => new ExtractedPage(2, 'Page 2 content', 14, ExtractionMethod::TEXT_LAYER),
        ];

        $savedCollection = $this->extractor->saveAllPages($document, $pages);

        expect($savedCollection)->toHaveCount(2)
            ->and($document->pages()->count())->toBe(2);
    });
});
