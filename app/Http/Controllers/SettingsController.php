<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AccountValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Learner settings (SPECIFICATION.md §9.2) and password change (§9.1).
 * The current values come with the user from GET /api/user.
 */
class SettingsController extends Controller
{
    use AccountValidationRules;

    public function update(Request $request): JsonResponse
    {
        $size = config('flashcards.session_size');
        $postpone = config('flashcards.postpone_sessions');

        $data = $request->validate([
            'session_size' => ['required', 'integer', "between:{$size['min']},{$size['max']}"],
            'postpone_sessions' => ['required', 'integer', "between:{$postpone['min']},{$postpone['max']}"],
        ], [], [
            'session_size' => 'words per session',
            'postpone_sessions' => 'postponement',
        ]);

        $request->user()->update($data);

        return response()->json(['user' => $request->user()->toApi(), 'message' => 'Settings saved.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRules(),
        ], [], [
            'password' => 'new password',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed.']);
    }
}
