<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * The logged-in user, or null. The React app asks for it when it starts.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = self::checkCredentials($request);

        // Accounts must be activated by email first (SPECIFICATION.md §9.1).
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your account is not activated yet. Open the link in the email we sent you.',
                'code' => 'not_activated',
            ], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->toApi()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * The user with the typed username and password, without logging them in.
     */
    public static function checkCredentials(Request $request): User
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Usernames are case-insensitive through the column's collation.
        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'username' => 'Wrong username or password.',
            ]);
        }

        return Auth::getLastAttempted();
    }
}
