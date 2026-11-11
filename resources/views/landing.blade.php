{{-- Public landing page (SPECIFICATION.md §10.2). All app screens are in the React app under /app. --}}
<!DOCTYPE html>
{{-- The theme cookie is set by the button in the app's header (resources/js/theme.ts). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if (request()->cookie('theme') === 'dark') data-theme="dark"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Learn words with flashcards</title>
    <meta name="description" content="Learn foreign words by typing their translation. The app repeats the words you get wrong.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite('resources/css/app.css')
</head>
<body>
    <header class="site-header">
        <a href="{{ url('/') }}" class="brand">{{ config('app.name') }}</a>
        <nav class="header-end">
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
