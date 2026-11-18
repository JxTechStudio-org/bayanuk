<?php

namespace App\Policies;

use App\Exceptions\ApiException;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentPolicy
{
    /**
     * Authorize access to a document by its owner (signed-in user or guest with matching token).
     *
     * In accordance with Technical Plan section 8 and Integration Contract section 4:
     * - An expired document returns 410 document_expired.
     * - A document that belongs to another owner returns 404 document_not_found (never revealing existence).
     *
     * @throws ApiException
     */
    public function authorize(Request $request, Document $document): void
    {
        // 1. Ownership check: Authenticated user
        $user = $request->user();
        if ($user !== null) {
            if ($document->user_id !== $user->id) {
                throw new ApiException('document_not_found', 404);
            }
        } else {
            // 2. Ownership check: Guest token
            $guestToken = $request->header('X-Guest-Token');
            $guestHash = is_string($guestToken) && $guestToken !== '' ? hash('sha256', $guestToken) : null;

            if ($guestHash === null || $document->guest_token_hash !== $guestHash) {
                throw new ApiException('document_not_found', 404);
            }
        }

        // 3. Expired check: Only legitimate owner reaches this check (preventing existence leaks, Technical Plan section 8)
        if ($document->expires_at && $document->expires_at->isPast()) {
            throw new ApiException('document_expired', 410);
        }
    }
}
