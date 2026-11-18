<?php

namespace App\Services\Extraction;

use Symfony\Component\Process\Process;

class PageCounter
{
    /**
     * Count the pages of a PDF file on disk.
     */
    public function count(string $filePath, ?string $mimeType = null): int
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return 1;
        }

        return $this->countPdfPages($filePath);
    }

    /**
     * Count pages in a PDF file using pdfinfo with regex fallback.
     */
    private function countPdfPages(string $filePath): int
    {
        // Try pdfinfo CLI first
        $process = new Process(['pdfinfo', $filePath]);
        $process->run();

        if ($process->isSuccessful()) {
            if (preg_match('/Pages:\s+(\d+)/', $process->getOutput(), $matches)) {
                return (int) $matches[1];
            }
        }

        // Fallback: search for /Count in /Pages dictionary or count /Type /Page objects
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 1;
        }

        if (preg_match('/\/Type\s*\/Pages\b[^\/]*\/Count\s+(\d+)/s', $content, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match_all('/\/Type\s*\/Page\b[^s]/', $content, $matches)) {
            return max(1, count($matches[0]));
        }

        return 1;
    }
}
