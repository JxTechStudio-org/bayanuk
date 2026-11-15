<?php

namespace App\Http\Requests;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class UploadDocumentRequest extends FormRequest
{
    public const MAX_BYTES = 10485760; // 10 MB

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    /**
     * Validate the file by its size and real content (magic bytes / mime inspection).
     */
    protected function passedValidation(): void
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        // 1. File size check: over 10 MB returns file_too_large (422)
        if ($file->getSize() > self::MAX_BYTES) {
            throw new ApiException('file_too_large', 422);
        }

        // 2. File content inspection (not extension)
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new ApiException('file_type_not_supported', 422);
        }

        if (! $this->isValidDocumentContent($realPath, $file)) {
            throw new ApiException('file_type_not_supported', 422);
        }
    }

    /**
     * Inspect file content to ensure it is a genuine PDF or DOCX file.
     */
    private function isValidDocumentContent(string $filePath, UploadedFile $file): bool
    {
        // Check magic bytes / mime type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : $file->getMimeType();
        if ($finfo) {
            finfo_close($finfo);
        }

        // PDF check: MIME must be application/pdf and start with %PDF-
        if ($mime === 'application/pdf') {
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $header = fread($handle, 5);
                fclose($handle);

                return $header === '%PDF-';
            }

            return false;
        }

        // DOCX check
        $docxMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip',
        ];

        if (in_array($mime, $docxMimes, true)) {
            // Verify it is a valid zip archive containing word/document.xml or [Content_Types].xml
            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $hasWordDoc = $zip->locateName('word/document.xml') !== false;
                $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
                $zip->close();

                return $hasWordDoc || $hasContentTypes;
            }
        }

        return false;
    }
}
