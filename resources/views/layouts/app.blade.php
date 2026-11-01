<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('title')
            @yield('title') ·
        @endif
        {{ config('app.name') }}
    </title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <header class="site-header">
        <a href="{{ url('/') }}" class="brand">{{ config('app.name') }}</a>

        @auth
            <nav class="nav">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.cards.index') }}">Cards</a>
                    <a href="{{ route('admin.languages.index') }}">Languages</a>
                    <a href="{{ route('admin.import.index') }}">Import / export</a>
                @endif
                <a href="{{ route('learn.index') }}">Learn</a>
                <a href="{{ route('settings.edit') }}">Settings</a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout">
                @csrf
                <span class="muted">{{ auth()->user()->username }}</span>
                <button type="submit" class="link-button">Log out</button>
            </form>
        @endauth
    </header>

    <main class="container">
        @if (session('status'))
            <p class="notice">{{ session('status') }}</p>
        @endif
        @if (session('warning'))
            <p class="notice warning">{{ session('warning') }}</p>
        @endif
        @if (session('error'))
            <p class="notice danger">{{ session('error') }}</p>
        @endif

        @yield('content')
    </main>
</body>
</html>
