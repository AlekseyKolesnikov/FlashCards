{{-- Loads the React app (resources/js). Everything it shows comes from the JSON API. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @php
        // Paths include the folder the app is served from, e.g. "/flashcards/app" (SPECIFICATION.md §10.2).
        $settings = [
            'appName' => config('app.name'),
            'landingUrl' => url('/'),
            'appPath' => parse_url(url('/app'), PHP_URL_PATH),
            'apiPath' => parse_url(url('/api'), PHP_URL_PATH),
            'registrationOpen' => (bool) config('flashcards.registration_open'),
            'complexity' => config('flashcards.complexity'),
            'sessionSize' => config('flashcards.session_size'),
            'postponeSessions' => config('flashcards.postpone_sessions'),
        ];
    @endphp
    <script>
        window.flashcards = @json($settings);
    </script>
    @viteReactRefresh
    @vite('resources/js/main.tsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>
