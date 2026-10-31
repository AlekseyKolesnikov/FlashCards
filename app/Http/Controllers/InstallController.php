<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\AccountValidationRules;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * One-time install page for hosts without SSH (SPECIFICATION.md §10.3):
 * runs the migrations and creates the first admin. Returns 404 without the
 * correct token and once an admin exists.
 */
class InstallController extends Controller
{
    use AccountValidationRules;

    public function show(Request $request): View
    {
        $token = $request->query('token');
        $this->ensureAvailable($token);

        return view('install', ['token' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAvailable($request->input('token'));

        // Before validation, because the unique rule needs the users table.
        Artisan::call('migrate', ['--force' => true]);

        $data = $request->validate($this->accountRules());

        $user = new User($data);
        $user->role = Role::Admin;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    private function ensureAvailable(mixed $token): void
    {
        $expected = config('flashcards.install_token');

        abort_if(! $expected || ! is_string($token) || ! hash_equals($expected, $token), 404);
        abort_if(Schema::hasTable('users') && User::where('role', Role::Admin)->exists(), 404);
    }
}
