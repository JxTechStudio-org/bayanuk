<?php

namespace App\Jobs\Pipeline;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Extraction\PageCounter;
use App\Services\Security\VirusScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Step 1 in the document processing pipeline (Technical Plan section 5).
 *
 * Runs ClamAV safety scan and verifies page count <= 30.
 * On failure: document status becomes rejected, status_detail holds reason,
 * and the uploaded file is deleted from private storage.
 */
class ScanFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function handle(?PageCounter $pageCounter = null, ?VirusScanner $virusScanner = null): void
    {
        $pageCounter = $pageCounter ?? app(PageCounter::class);
        $virusScanner = $virusScanner ?? app(VirusScanner::class);

        // 1. Mark scanning status so frontend polling can display Stage 1 "Checking file"
        $this->document->update([
            'status' => DocumentStatus::SCANNING,
        ]);

        $disk = Storage::disk('s3');
        if (! $disk->exists($this->document->storage_key)) {
            return;
        }

        $fileContent = $disk->get($this->document->storage_key);
        if ($fileContent === null) {
            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bayanuk_scan_');
        if ($tmpPath === false) {
            return;
        }

        file_put_contents($tmpPath, $fileContent);

        try {
            // 2. ClamAV malware scan: an unsafe file is marked rejected with unsafe_file
            if (! $virusScanner->isSafe($tmpPath)) {
                $this->document->update([
                    'status' => DocumentStatus::REJECTED,
                    'status_detail' => 'unsafe_file',
                    'page_count' => null,
                ]);

                $disk->delete($this->document->storage_key);

                return;
            }

            // 3. Count pages
            $pageCount = $pageCounter->count($tmpPath, $this->document->mime_type);

            $maxPages = (int) config('bayanuk.limits.max_pages', 30);
            if ($pageCount > $maxPages) {
                // Reject document with too_many_pages and delete the file from storage
                $this->document->update([
                    'status' => DocumentStatus::REJECTED,
                    'status_detail' => 'too_many_pages',
                    'page_count' => null,
                ]);

                $disk->delete($this->document->storage_key);

                return;
            }

            // Valid page count: save page_count to document
            $this->document->update([
                'page_count' => $pageCount,
            ]);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
