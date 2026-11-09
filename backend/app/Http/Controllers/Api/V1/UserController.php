<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeletionReason;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UserController extends Controller
{
    // GET /api/v1/me
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'locale' => $user->locale,
            'keep_documents' => $user->keep_documents,
            'created_at' => $user->created_at,
        ]);
    }

    // PATCH /api/v1/me
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'sometimes|in:ar,en',
            'keep_documents' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'id' => $user->id,
            'locale' => $user->locale,
            'keep_documents' => $user->keep_documents,
        ]);
    }

    // DELETE /api/v1/me
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $disk = Storage::disk('s3');

        // 1. Remove every document file from RustFS first. A database row is only removed after its
        //    file is confirmed gone, so a storage failure stops here and the account stays intact.
        $deletedDocumentIds = [];

        foreach ($user->documents as $document) {
            if (! $this->deleteStoredFile($disk, $document->storage_key)) {
                report(new RuntimeException("Account deletion stopped: file of document {$document->id} could not be removed."));

                throw new ApiException('server_error', 500);
            }

            $deletedDocumentIds[] = $document->id;
        }

        // 2. Write the deletion log and delete the database records (the cascade removes documents,
        //    analyses, chunks, chats). usage_events stay, without content, for cost reporting.
        DB::transaction(function () use ($user, $deletedDocumentIds) {
            foreach ($deletedDocumentIds as $documentId) {
                DB::table('deletion_logs')->insert([
                    'document_id' => $documentId,
                    'reason' => DeletionReason::ACCOUNT_DELETED->value,
                    'file_deleted' => true,
                    'deleted_at' => now(),
                ]);
            }

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });

        // 3. End this browser's session (SPA cookie). There are no personal access tokens in this project.
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(null, 204);
    }

    /**
     * Delete one file and confirm it is gone.
     */
    private function deleteStoredFile(Filesystem $disk, ?string $storageKey): bool
    {
        if ($storageKey === null || $storageKey === '') {
            return true;
        }

        try {
            return $disk->delete($storageKey) && ! $disk->exists($storageKey);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
