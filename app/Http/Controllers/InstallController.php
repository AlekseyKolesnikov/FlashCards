<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\AccountValidationRules;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * One-time install for hosts without SSH (SPECIFICATION.md §10.5): runs the migrations
 * and creates the first admin. Returns 404 without the correct token and once an admin exists.
 */
class InstallController extends Controller
{
    use AccountValidationRules;

    public function show(Request $request): JsonResponse
    {
        $this->ensureAvailable($request->query('token'));

        return response()->json(['available' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAvailable($request->input('token'));

        // Before validation, because the unique rules need the users table.
        Artisan::call('migrate', ['--force' => true]);

        $data = $request->validate($this->accountRules());

        // The install token proves ownership, so the admin needs no activation email.
        $user = new User($data);
        $user->role = Role::Admin;
        $user->email_verified_at = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->toApi()], 201);
    }

    private function ensureAvailable(mixed $token): void
    {
        $expected = config('flashcards.install_token');

        abort_if(! $expected || ! is_string($token) || ! hash_equals($expected, $token), 404);
        abort_if(Schema::hasTable('users') && User::where('role', Role::Admin)->exists(), 404);
    }
}
