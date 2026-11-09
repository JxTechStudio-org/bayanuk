<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Guest token handling shared by every sign-in flow (Integration Contract section 5).
 */
class GuestDocumentClaimer
{
    /** A guest token is a random 64-character alphanumeric string issued by the backend. */
    public const FORMAT = '/^[A-Za-z0-9]{64}$/';

    public const HEADER = 'X-Guest-Token';

    public function isValid(mixed $token): bool
    {
        return is_string($token) && preg_match(self::FORMAT, $token) === 1;
    }

    /**
     * Read the guest token from the X-Guest-Token header.
     *
     * Returns null when the header is absent (signing in without a guest token is a normal case)
     * and throws invalid_guest_token (400) when it is present but not in the right format.
     */
    public function tokenFromRequest(Request $request): ?string
    {
        $token = $request->header(self::HEADER);

        if ($token === null || $token === '') {
            return null;
        }

        if (! $this->isValid($token)) {
            throw new ApiException('invalid_guest_token', 400);
        }

        return $token;
    }

    /**
     * Move the guest's documents to the user: user_id is set and the guest hash is cleared.
     *
     * @return int Number of documents claimed
     */
    public function claim(User $user, ?string $guestToken): int
    {
        if ($guestToken === null || $guestToken === '') {
            return 0;
        }

        return Document::where('guest_token_hash', hash('sha256', $guestToken))
            ->whereNull('user_id')
            ->update([
                'user_id' => $user->id,
                'guest_token_hash' => null,
            ]);
    }
}
