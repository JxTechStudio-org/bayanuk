<?php

namespace App\Services\Security;

interface VirusScanner
{
    /**
     * Scan a file for malware.
     *
     * @return bool True if safe/clean, false if infected/unsafe.
     */
    public function isSafe(string $filePath): bool;
}
