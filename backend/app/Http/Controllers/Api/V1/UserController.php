<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeletionReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        DB::transaction(function () use ($user) {
            // 1. Delete all associated user documents from RustFS storage first
            foreach ($user->documents as $document) {
                if ($document->storage_key && Storage::disk('s3')->exists($document->storage_key)) {
                    Storage::disk('s3')->delete($document->storage_key);
                }

                // 2. Write an audit entry to deletion_logs with reason account_deleted
                DB::table('deletion_logs')->insert([
                    'document_id' => $document->id,
                    'reason' => DeletionReason::ACCOUNT_DELETED->value,
                    'file_deleted' => true,
                    'deleted_at' => now(),
                ]);
            }

            // 3. Delete the user record (DB cascade will remove documents, analyses, chunks, etc.)
            // Note: usage_events rows remain intact without content for cost reporting.
            $user->delete();
        });

        // 4. Terminate the current Sanctum token/session
        $user->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
