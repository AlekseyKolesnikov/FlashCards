@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    @php
        $size = config('flashcards.session_size');
        $postpone = config('flashcards.postpone_sessions');
    @endphp

    <form method="POST" action="{{ route('settings.update') }}" class="card form narrow">
        @csrf
        @method('PUT')
        <h1>Settings</h1>

        <label for="session_size">Words per session</label>
        <input id="session_size" name="session_size" type="number" required
               min="{{ $size['min'] }}" max="{{ $size['max'] }}"
               value="{{ old('session_size', $user->sessionSize()) }}">
        <p class="hint">{{ $size['min'] }}–{{ $size['max'] }}. Applies from your next new session.</p>
        @error('session_size') <p class="error">{{ $message }}</p> @enderror

        <label for="postpone_sessions">Learned words postponed for (sessions)</label>
        <input id="postpone_sessions" name="postpone_sessions" type="number" required
               min="{{ $postpone['min'] }}" max="{{ $postpone['max'] }}"
               value="{{ old('postpone_sessions', $user->postponeSessions()) }}">
        <p class="hint">{{ $postpone['min'] }}–{{ $postpone['max'] }}. A learned word comes back after this many sessions.</p>
        @error('postpone_sessions') <p class="error">{{ $message }}</p> @enderror

        <button type="submit" class="button">Save</button>
    </form>
@endsection
