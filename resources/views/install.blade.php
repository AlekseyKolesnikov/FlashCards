@extends('layouts.app')

@section('title', 'Install')

@section('content')
    <form method="POST" action="{{ route('install') }}" class="card form narrow">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1>Install</h1>
        <p class="muted">This sets up the database and creates the first admin account.
            The page disappears once an admin exists.</p>

        @include('auth._account-fields')

        <button type="submit" class="button">Install and create admin</button>
    </form>
@endsection
