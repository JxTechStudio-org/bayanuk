<?php

namespace App\Services\Extraction;

use Symfony\Component\Process\Process;
use ZipArchive;

class PageCounter
{
    /**
     * Count the pages of a PDF or DOCX file on disk.
     */
    public function count(string $filePath, ?string $mimeType = null): int
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return 1;
        }

        // 1. PDF page count
        if ($mimeType === 'application/pdf' || str_ends_with(strtolower($filePath), '.pdf')) {
            return $this->countPdfPages($filePath);
        }

        // 2. DOCX page count
        if (str_ends_with(strtolower($filePath), '.docx') || in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ], true)) {
            return $this->countDocxPages($filePath);
        }

        return 1;
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

    /**
     * Count pages in a DOCX file from docProps/app.xml.
     */
    private function countDocxPages(string $filePath): int
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) === true) {
            $appXml = $zip->getFromName('docProps/app.xml');
            $zip->close();

            if ($appXml !== false && preg_match('/<Pages>(\d+)<\/Pages>/', $appXml, $matches)) {
                return max(1, (int) $matches[1]);
            }
        }

        return 1;
    }
}
