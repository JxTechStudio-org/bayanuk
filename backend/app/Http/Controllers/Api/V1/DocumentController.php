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
use App\Models\UsageEvent;
use App\Policies\DocumentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
     * Enforces daily AI budget ($2), duplicate file handling, and daily upload limits (guest 2, user 10).
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $guestToken = $request->header('X-Guest-Token');
        $guestTokenHash = $user ? null : hash('sha256', (string) $guestToken);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $sha256 = hash_file('sha256', $file->getRealPath());

        // 1. Daily AI Budget Check (Item 10)
        // Hard daily limit ($2), resets at 00:00 Asia/Riyadh (UTC+3).
        // Refused with 429 ai_budget_reached; no document is created and no upload is used.
        $startOfDayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->setTimezone('UTC');
        $dailyBudget = (float) config('bayanuk.ai_budget.daily_usd', 2.00);
        $spentToday = (float) UsageEvent::query()
            ->where('created_at', '>=', $startOfDayRiyadh)
            ->sum('cost_usd');

        if ($spentToday >= $dailyBudget) {
            throw new ApiException('ai_budget_reached', 429);
        }

        // 2. Duplicate Upload Check (Item 9)
        // Same file (sha256) by the same owner (user or guest).
        $existing = Document::query()
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $user, fn ($q) => $q->where('guest_token_hash', $guestTokenHash))
            ->where('sha256', $sha256)
            ->latest('created_at')
            ->first();

        if ($existing !== null) {
            if ($existing->status === DocumentStatus::FAILED) {
                // Retry limit: 2 per document
                $retryKey = "document_retry:{$existing->id}";
                $retries = (int) Cache::get($retryKey, 0);

                if ($retries < 2) {
                    Cache::put($retryKey, $retries + 1, now()->addDays(30));
                    $existing->update([
                        'status' => DocumentStatus::UPLOADED,
                        'status_detail' => null,
                    ]);

                    ScanFile::dispatch($existing);

                    return response()->json([
                        'id' => $existing->id,
                        'status' => DocumentStatus::UPLOADED->value,
                    ], 202);
                }

                return response()->json([
                    'id' => $existing->id,
                    'status' => DocumentStatus::FAILED->value,
                ], 202);
            }

            // For ready, rejected, or still processing: return existing document
            return response()->json([
                'id' => $existing->id,
                'status' => $existing->status->value,
            ], 202);
        }

        // 3. Daily Upload Limits Check (Item 8)
        // Guests: 2 uploads a day (per device/guest_token and IP). Signed-in: 10 uploads a day.
        // Resets at 00:00 Asia/Riyadh. Only new, distinct documents count.
        $dailyLimit = $user
            ? (int) config('bayanuk.upload_limits.user_daily_uploads', 10)
            : (int) config('bayanuk.upload_limits.guest_daily_uploads', 2);

        $ipHash = hash('sha256', (string) $request->ip());

        $uploadsTodayCount = UsageEvent::query()
            ->where('event', 'upload')
            ->where('created_at', '>=', $startOfDayRiyadh)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $user, fn ($q) => $q->where(function ($sub) use ($guestTokenHash, $ipHash) {
                $sub->where('guest_token_hash', $guestTokenHash)
                    ->orWhere('ip_hash', $ipHash);
            }))
            ->count();

        if ($uploadsTodayCount >= $dailyLimit) {
            throw new ApiException('upload_limit_reached', 429);
        }

        // 4. Store file in RustFS (S3) and create document
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file->getRealPath()) : $file->getMimeType();
        if ($finfo) {
            finfo_close($finfo);
        }

        $expiresAt = $user
            ? now()->addDays(config('bayanuk.retention.user_days', 30))
            : now()->addHours(config('bayanuk.retention.guest_hours', 24));

        $uuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'docx'], true)) {
            $ext = ($mimeType === 'application/pdf') ? 'pdf' : 'docx';
        }
        $filename = "{$uuid}.{$ext}";
        $storageKey = "documents/{$filename}";

        Storage::disk('s3')->putFileAs('documents', $file, $filename);

        $document = Document::create([
            'id' => $uuid,
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

        // Record upload usage event
        UsageEvent::create([
            'user_id' => $user?->id,
            'guest_token_hash' => $guestTokenHash,
            'ip_hash' => $ipHash,
            'event' => 'upload',
            'cost_usd' => 0,
            'created_at' => now(),
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
