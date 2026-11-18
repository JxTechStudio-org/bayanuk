<?php

namespace App\Http\Requests;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
     * Inspect file content to ensure it is a genuine PDF file matching allowed_mimes.
     */
    private function isValidDocumentContent(string $filePath, UploadedFile $file): bool
    {
        $allowedMimes = config('bayanuk.limits.allowed_mimes', ['pdf']);

        // Check magic bytes / mime type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : $file->getMimeType();
        if ($finfo) {
            finfo_close($finfo);
        }

        // PDF check: MIME must be application/pdf and content must start with %PDF- header
        if (in_array('pdf', $allowedMimes, true) && $mime === 'application/pdf') {
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $header = fread($handle, 5);
                fclose($handle);

                return $header === '%PDF-';
            }

            return false;
        }

        return false;
    }
}
