<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Learner settings (SPECIFICATION.md §9.2).
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
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

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
