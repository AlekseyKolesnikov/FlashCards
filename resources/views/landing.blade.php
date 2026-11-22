{{-- Public landing page (SPECIFICATION.md §10.2). All app screens are in the React app under /app. --}}
<!DOCTYPE html>
{{-- The theme cookie is set by the theme button here and in the app's header (resources/js/theme.ts). --}}
@php($dark = request()->cookie('theme') === 'dark')
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if ($dark) data-theme="dark"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Learn words with flashcards</title>
    <meta name="description" content="Learn foreign words by typing their translation. The app repeats the words you get wrong.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/landing.ts'])
</head>
<body>
    <header class="site-header">
        <a href="{{ url('/') }}" class="brand">{{ config('app.name') }}</a>
        <nav class="header-end">
            {{-- Same button as in the app (resources/js/components/ThemeToggle.tsx); resources/js/landing.ts makes it work.
                 The cookie path is the login cookies' path, as in the app. --}}
            @php($label = 'Switch to ' . ($dark ? 'light' : 'dark') . ' theme')
            <button type="button" class="theme-toggle" aria-label="{{ $label }}" title="{{ $label }}" data-cookie-path="{{ config('session.path') }}">
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                </svg>
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                </svg>
            </button>
            @auth
                <a href="{{ url('/app') }}">Open the app</a>
            @else
                <a href="{{ url('/app/login') }}">Log in</a>
            @endauth
        </nav>
    </header>

    <main class="container landing">
        <h1>Learn foreign words with flashcards</h1>
        <p class="lead">See a word, type its translation, and check it at once.
            Words you get wrong come back in your next sessions until you know them;
            words you know take a break before they return.</p>

        <ul class="features">
            <li><strong>Type, don’t guess.</strong> Each answer is checked letter by letter, accents included.</li>
            <li><strong>Your own pace.</strong> Choose how many words a session has and how long learned words rest.</li>
            <li><strong>Both directions.</strong> Learn every language pair from either side.</li>
        </ul>

        @if ($pairs)
            <h2>Available words to learn</h2>
            <ul class="pair-sizes">
                @foreach ($pairs as $pair)
                    <li>{{ $pair['label'] }}: <strong>{{ number_format($pair['cards']) }}</strong> {{ Str::plural('word', $pair['cards']) }}</li>
                @endforeach
            </ul>
        @endif

        <div class="buttons">
            @auth
                <a href="{{ url('/app') }}" class="button">Open the app</a>
            @else
                @if (config('flashcards.registration_open'))
                    <a href="{{ url('/app/register') }}" class="button">Create an account</a>
                @endif
                <a href="{{ url('/app/login') }}" class="button secondary">Log in</a>
            @endauth
        </div>
    </main>
</body>
</html>
