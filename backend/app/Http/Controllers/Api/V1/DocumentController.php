<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Jobs\Pipeline\ScanFile;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * POST /api/v1/documents
     *
     * Accept a document upload (PDF/DOCX up to 10MB) for guests or authenticated users.
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $guestToken = $request->header('X-Guest-Token');
        $guestTokenHash = $user ? null : hash('sha256', $guestToken);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $sha256 = hash_file('sha256', $file->getRealPath());

        // Detect real mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file->getRealPath()) : $file->getMimeType();
        if ($finfo) {
            finfo_close($finfo);
        }

        // Determine expiration: 24h for guests, 30 days for users
        $expiresAt = $user
            ? now()->addDays(config('bayanuk.retention.user_days', 30))
            : now()->addHours(config('bayanuk.retention.guest_hours', 24));

        // Generate unique storage key and store in RustFS (S3 disk)
        $uuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'docx'], true)) {
            $ext = ($mimeType === 'application/pdf') ? 'pdf' : 'docx';
        }
        $filename = "{$uuid}.{$ext}";
        $storageKey = "documents/{$filename}";

        Storage::disk('s3')->putFileAs('documents', $file, $filename);

        $document = Document::create([
            'user_id' => $user?->id,
            'guest_token_hash' => $guestTokenHash,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'sha256' => $sha256,
            'storage_key' => $storageKey,
            'status' => DocumentStatus::UPLOADED,
            'status_detail' => null,
            'expires_at' => $expiresAt,
        ]);

        // Dispatch pipeline Step 1 (ScanFile)
        ScanFile::dispatch($document);

        return response()->json([
            'id' => $document->id,
            'status' => $document->status->value,
        ], 202);
    }
}
