<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class AuthController extends Controller
{
    public function googleRedirect(Request $request): JsonResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }
}
