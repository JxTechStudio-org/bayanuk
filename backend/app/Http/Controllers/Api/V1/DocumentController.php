<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeletionReason;
use App\Enums\DocumentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\Pipeline\ScanFile;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentController extends Controller
{
    /**
     * GET /api/v1/documents
     *
     * List authenticated user's documents paginated (Integration Contract section 7).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw new ApiException('unauthorized', 401);
        }

        $paginated = $user->documents()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (Document $doc) => [
                'id' => $doc->id,
                'original_filename' => $doc->original_filename,
                'status' => $doc->status->value,
                'status_detail' => $doc->status_detail,
                'document_type' => $doc->document_type?->value,
                'page_count' => $doc->page_count,
                'created_at' => $doc->created_at?->toIso8601String(),
                'expires_at' => $doc->expires_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

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

    /**
     * GET /api/v1/documents/{id}/status
     *
     * Lightweight status polling endpoint (Integration Contract section 6).
     */
    public function status(Request $request, string $id, DocumentPolicy $policy): JsonResponse
    {
        $document = Document::findOrFail($id);
        $policy->authorize($request, $document);

        return response()->json([
            'id' => $document->id,
            'status' => $document->status->value,
            'status_detail' => $document->status_detail,
        ]);
    }

    /**
     * GET /api/v1/documents/{id}
     *
     * Full document result with analysis, terms and findings (Integration Contract section 13).
     */
    public function show(Request $request, string $id, DocumentPolicy $policy): JsonResponse
    {
        $document = Document::with(['latestAnalysis.extractedTerms', 'latestAnalysis.findings'])->findOrFail($id);
        $policy->authorize($request, $document);

        return response()->json((new DocumentResource($document))->resolve($request));
    }

    /**
     * GET /api/v1/documents/{id}/file
     *
     * Returns a signed URL valid for 5 minutes to view the original file.
     */
    public function file(Request $request, string $id, DocumentPolicy $policy): JsonResponse
    {
        $document = Document::findOrFail($id);
        $policy->authorize($request, $document);

        $expiresAt = now()->addMinutes(5);
        $url = Storage::disk('s3')->temporaryUrl($document->storage_key, $expiresAt);

        return response()->json([
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/v1/documents/{id}
     *
     * Deletes file from RustFS first, cascades database rows, and logs deletion (Database Schema section 7).
     */
    public function destroy(Request $request, string $id, DocumentPolicy $policy): JsonResponse
    {
        $document = Document::findOrFail($id);
        $policy->authorize($request, $document);

        $disk = Storage::disk('s3');

        // 1. Remove file from RustFS storage first
        if ($document->storage_key !== '' && $disk->exists($document->storage_key)) {
            $deleted = $disk->delete($document->storage_key);
            if (! $deleted) {
                report(new RuntimeException("Storage deletion failed for document {$document->id}"));

                throw new ApiException('server_error', 500);
            }
        }

        // 2. Cascade delete database records and record in deletion_logs
        DB::transaction(function () use ($document) {
            DB::table('deletion_logs')->insert([
                'document_id' => $document->id,
                'reason' => DeletionReason::USER_REQUEST->value,
                'file_deleted' => true,
                'deleted_at' => now(),
            ]);

            $document->delete();
        });

        return response()->json(null, 204);
    }
}
