<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Concerns\AccountValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Self-registration of learner accounts. Returns 404 while registration is closed.
 */
class RegisterController extends Controller
{
    use AccountValidationRules;

    public function create(): View
    {
        abort_unless(config('flashcards.registration_open'), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('flashcards.registration_open'), 404);

        $data = $request->validate($this->accountRules());

        $user = new User($data);
        $user->role = Role::Learner;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
