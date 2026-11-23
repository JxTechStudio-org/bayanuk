<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionMethod;
use App\Models\Document;
use App\Services\AI\AIService;
use App\Services\AI\BudgetGuard;
use Symfony\Component\Process\Process;
use Throwable;

class OcrExtractor
{
    public function __construct(
        private readonly ArabicTextChecker $textChecker = new ArabicTextChecker,
        private readonly TextNormalizer $normalizer = new TextNormalizer,
        private readonly PiiMasker $piiMasker = new PiiMasker,
        private readonly BudgetGuard $budgetGuard = new BudgetGuard,
        private readonly ?AIService $aiService = null,
    ) {}

    /**
     * Extract text from a page using local Tesseract OCR, falling back to Gemini Vision.
     */
    public function extractPage(string $pdfPath, int $page, ?Document $document = null): ExtractedPage
    {
        $imagePath = $this->renderPageToImage($pdfPath, $page);
        if ($imagePath === null || ! file_exists($imagePath)) {
            return new ExtractedPage($page, '', 0, null, true);
        }

        try {
            // Tier 1: Local Tesseract OCR (ara+eng) - Free, runs locally
            $tesseractOutput = $this->runTesseract($imagePath);

            if ($this->textChecker->hasSufficientText($tesseractOutput) && ! $this->textChecker->isGarbled($tesseractOutput)) {
                $cleaned = str_replace("\f", '', $tesseractOutput);
                $normalized = $this->normalizer->normalize($cleaned);
                $masked = $this->piiMasker->mask($normalized);

                if ($document !== null) {
                    $document->update(['is_scanned' => true]);
                }

                return new ExtractedPage(
                    pageNumber: $page,
                    text: $masked,
                    charCount: mb_strlen($masked),
                    extractionMethod: ExtractionMethod::OCR,
                    needsOcr: false,
                );
            }

            // Tier 2: Gemini Vision Fallback
            // Before any AI call, check the daily AI budget ($2.00)
            if ($this->budgetGuard->isBudgetReached()) {
                if ($document !== null) {
                    $document->update([
                        'status_detail' => 'ai_budget_reached',
                    ]);
                }

                return new ExtractedPage(
                    pageNumber: $page,
                    text: '',
                    charCount: 0,
                    extractionMethod: null,
                    needsOcr: true,
                );
            }

            // AI budget is available: resolve AIService and invoke vision OCR fallback
            /** @var AIService|null $ai */
            $ai = $this->aiService ?? (app()->bound(AIService::class) ? app(AIService::class) : null);
            if ($ai === null) {
                return new ExtractedPage($page, '', 0, null, true);
            }

            $visionOutput = $ai->ocrFallback($imagePath);

            if ($this->textChecker->hasSufficientText($visionOutput) && ! $this->textChecker->isGarbled($visionOutput)) {
                $cleaned = str_replace("\f", '', $visionOutput);
                $normalized = $this->normalizer->normalize($cleaned);
                $masked = $this->piiMasker->mask($normalized);

                if ($document !== null) {
                    $document->update(['is_scanned' => true]);
                }

                return new ExtractedPage(
                    pageNumber: $page,
                    text: $masked,
                    charCount: mb_strlen($masked),
                    extractionMethod: ExtractionMethod::VISION,
                    needsOcr: false,
                );
            }

            return new ExtractedPage($page, '', 0, null, true);
        } finally {
            @unlink($imagePath);
        }
    }

    /**
     * Render a single PDF page into a PNG image using pdftoppm.
     */
    protected function renderPageToImage(string $pdfPath, int $page): ?string
    {
        $tempPrefix = tempnam(sys_get_temp_dir(), "bayanuk_page_{$page}_");
        if ($tempPrefix === false) {
            return null;
        }
        @unlink($tempPrefix);

        try {
            $process = new Process([
                'pdftoppm',
                '-png',
                '-r', '200',
                '-f', (string) $page,
                '-l', (string) $page,
                $pdfPath,
                $tempPrefix,
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $matches = glob($tempPrefix.'-*.png');
            if ($matches !== false && ! empty($matches)) {
                return $matches[0];
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Execute local Tesseract OCR on a page image.
     */
    protected function runTesseract(string $imagePath): string
    {
        try {
            $process = new Process([
                'tesseract',
                $imagePath,
                'stdout',
                '-l', 'ara+eng',
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
