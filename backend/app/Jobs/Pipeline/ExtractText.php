<?php

namespace App\Jobs\Pipeline;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Extraction\ExtractedPage;
use App\Services\Extraction\OcrExtractor;
use App\Services\Extraction\PageCounter;
use App\Services\Extraction\PdfTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Step 2 in the document processing pipeline (Technical Plan section 5).
 *
 * Extracts text per page from PDF documents, performs OCR / Vision fallback for
 * scanned or unreadable pages, normalizes numerals, masks PII, and persists
 * extracted pages to document_pages.
 *
 * Status: extracting during this step.
 * On failure: document status becomes failed with status_detail = 'file_unreadable'.
 * If daily AI budget is reached during Vision fallback: pauses with status_detail = 'ai_budget_reached'.
 */
class ExtractText implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function handle(
        ?PdfTextExtractor $pdfExtractor = null,
        ?OcrExtractor $ocrExtractor = null,
        ?PageCounter $pageCounter = null,
    ): void {
        $pdfExtractor = $pdfExtractor ?? app(PdfTextExtractor::class);
        $ocrExtractor = $ocrExtractor ?? app(OcrExtractor::class);
        $pageCounter = $pageCounter ?? app(PageCounter::class);

        // 1. Mark status as extracting so frontend polling shows Stage 2 "Reading document"
        $this->document->update([
            'status' => DocumentStatus::EXTRACTING,
        ]);

        $disk = Storage::disk('s3');
        if (! $disk->exists($this->document->storage_key)) {
            $this->markFailedAsUnreadable();

            return;
        }

        $fileContent = $disk->get($this->document->storage_key);
        if ($fileContent === null) {
            $this->markFailedAsUnreadable();

            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bayanuk_extract_');
        if ($tmpPath === false) {
            $this->markFailedAsUnreadable();

            return;
        }

        file_put_contents($tmpPath, $fileContent);

        try {
            $totalPages = $this->document->page_count ?? $pageCounter->count($tmpPath, $this->document->mime_type);
            if ($totalPages <= 0) {
                $this->markFailedAsUnreadable();

                return;
            }

            if ($this->document->page_count === null) {
                $this->document->update(['page_count' => $totalPages]);
            }

            /** @var array<int, ExtractedPage> $extractedPages */
            $extractedPages = [];
            $hasAnyReadableText = false;

            for ($page = 1; $page <= $totalPages; $page++) {
                $extractedPage = $pdfExtractor->extractPage($tmpPath, $page);

                if ($extractedPage->needsOcr) {
                    $extractedPage = $ocrExtractor->extractPage($tmpPath, $page, $this->document);

                    // If daily AI budget was reached during Gemini Vision fallback, pause processing
                    $this->document->refresh();
                    if ($this->document->status_detail === 'ai_budget_reached') {
                        return;
                    }
                }

                if ($extractedPage->charCount > 0 && trim($extractedPage->text) !== '') {
                    $hasAnyReadableText = true;
                }

                $extractedPages[$page] = $extractedPage;
            }

            // If no readable text could be extracted across all pages, mark as failed
            if (! $hasAnyReadableText) {
                $this->markFailedAsUnreadable();

                return;
            }

            // Persist all extracted pages to document_pages table
            $pdfExtractor->saveAllPages($this->document, $extractedPages);

        } catch (Throwable $e) {
            Log::error('ExtractText failed: '.$e->getMessage(), [
                'document_id' => $this->document->id,
                'exception' => $e,
            ]);

            $this->markFailedAsUnreadable();
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    protected function markFailedAsUnreadable(): void
    {
        $this->document->update([
            'status' => DocumentStatus::FAILED,
            'status_detail' => 'file_unreadable',
        ]);
    }
}
