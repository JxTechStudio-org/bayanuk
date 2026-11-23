<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionMethod;
use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractor
{
    public function __construct(
        private readonly PageCounter $pageCounter = new PageCounter,
        private readonly ArabicTextChecker $textChecker = new ArabicTextChecker,
        private readonly TextNormalizer $normalizer = new TextNormalizer,
        private readonly PiiMasker $piiMasker = new PiiMasker,
    ) {}

    /**
     * Extract text from all pages of a PDF file on disk.
     *
     * @return array<int, ExtractedPage> Keyed by 1-based page number
     */
    public function extractAll(string $pdfPath, ?int $pageCount = null): array
    {
        $totalPages = $pageCount ?? $this->pageCounter->count($pdfPath);
        $pages = [];

        for ($page = 1; $page <= $totalPages; $page++) {
            $pages[$page] = $this->extractPage($pdfPath, $page);
        }

        return $pages;
    }

    /**
     * Extract text from a single page of a PDF file.
     */
    public function extractPage(string $pdfPath, int $page): ExtractedPage
    {
        $rawText = $this->runPdftotext($pdfPath, $page);

        // Check if the page is empty, scanned, or contains broken/reversed Arabic
        if ($this->textChecker->needsOcr($rawText)) {
            return new ExtractedPage(
                pageNumber: $page,
                text: '',
                charCount: 0,
                extractionMethod: null,
                needsOcr: true,
            );
        }

        // Clean text, normalize numerals (٠-٩ -> 0-9), and mask PII identifiers
        $cleaned = str_replace("\f", '', $rawText);
        $normalized = $this->normalizer->normalize($cleaned);
        $masked = $this->piiMasker->mask($normalized);

        return new ExtractedPage(
            pageNumber: $page,
            text: $masked,
            charCount: mb_strlen($masked),
            extractionMethod: ExtractionMethod::TEXT_LAYER,
            needsOcr: false,
        );
    }

    /**
     * Persist an extracted page into the document_pages table.
     */
    public function savePage(Document $document, ExtractedPage $page): DocumentPage
    {
        /** @var DocumentPage $savedPage */
        $savedPage = $document->pages()->updateOrCreate(
            ['page_number' => $page->pageNumber],
            [
                'text' => $page->text,
                'char_count' => $page->charCount,
                'extraction_method' => $page->extractionMethod ?? ExtractionMethod::TEXT_LAYER,
            ]
        );

        return $savedPage;
    }

    /**
     * Persist multiple extracted pages to the database.
     *
     * @param  array<int, ExtractedPage>  $pages
     * @return Collection<int, DocumentPage>
     */
    public function saveAllPages(Document $document, array $pages): Collection
    {
        $saved = collect();

        foreach ($pages as $page) {
            $saved->push($this->savePage($document, $page));
        }

        return $saved;
    }

    /**
     * Run pdftotext CLI command for a single page.
     */
    protected function runPdftotext(string $pdfPath, int $page): string
    {
        try {
            $process = new Process([
                'pdftotext',
                '-f', (string) $page,
                '-l', (string) $page,
                $pdfPath,
                '-',
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            return $process->getOutput();
        } catch (Throwable) {
            return '';
        }
    }
}
