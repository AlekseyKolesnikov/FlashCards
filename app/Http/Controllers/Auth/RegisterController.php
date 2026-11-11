<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Concerns\AccountValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Self-registration of learner accounts with activation by email (SPECIFICATION.md §9.1).
 * Registration returns 404 while it is closed.
 */
class RegisterController extends Controller
{
    use AccountValidationRules;

    public function store(Request $request): JsonResponse
    {
        abort_unless(config('flashcards.registration_open'), 404);

        $data = $request->validate($this->accountRules());

        $user = new User($data);
        $user->role = Role::Learner;
        $user->save();

        // Not logged in: the account must be activated first.
        if (! $this->sendActivationLink($user)) {
            return response()->json([
                'message' => 'Your account was created, but the activation email could not be sent. '
                    .'Try to log in later to get a new one.',
            ], 201);
        }

        return response()->json([
            'message' => "We sent an activation link to {$user->email}. Open it to activate your account.",
        ], 201);
    }

    /**
     * The link from the activation email. It works without being logged in, because it is
     * often opened on another device, and always ends on the app's login page.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        $valid = $request->hasValidSignature()
            && $user?->email !== null
            && hash_equals(sha1($user->getEmailForVerification()), $hash);

        if (! $valid) {
            return redirect()->to(url('/app/login').'?activation=invalid');
        }

        $user->hasVerifiedEmail() || $user->markEmailAsVerified();

        return redirect()->to(url('/app/login').'?activated=1');
    }

    /**
     * Sends a new link. The typed username and password prove the account is the caller's.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = LoginController::checkCredentials($request);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your account is already activated. You can log in.']);
        }

        if (! $this->sendActivationLink($user)) {
            return response()->json(['message' => 'The activation email could not be sent. Try again later.'], 503);
        }

        return response()->json(['message' => "We sent a new activation link to {$user->email}."]);
    }

    /**
     * Mail is sent during the request, because shared hosts have no queue worker.
     */
    private function sendActivationLink(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
