@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="card form narrow">
        @csrf
        <h1>Log in</h1>

        <label for="username">Username</label>
        <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
        @error('username') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <button type="submit" class="button">Log in</button>

        @if (config('flashcards.registration_open'))
            <p class="muted">No account? <a href="{{ route('register') }}">Register</a></p>
        @endif
    </form>
@endsection
