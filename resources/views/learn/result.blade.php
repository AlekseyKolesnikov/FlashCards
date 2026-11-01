@extends('layouts.app')

@section('title', 'Learn')

@section('content')
    @include('learn._header', ['cardsLeft' => $result['cards_left']])

    <div class="card study">
        @if ($result['correct'])
            <p class="mark correct">✔ Correct</p>
        @else
            <p class="mark wrong">✘ {{ $result['typed'] === null ? 'Not known' : 'Incorrect' }}</p>
        @endif

        <p class="muted" lang="{{ $session->fromLang->code }}">{{ $prompt }}</p>
        <p class="prompt" lang="{{ $session->toLang->code }}">{{ $expected }}</p>

        @if (! $result['correct'] && $result['typed'] !== null)
            <p class="your-answer">Your answer: <span lang="{{ $session->toLang->code }}">{{ $result['typed'] }}</span></p>
        @endif

        @if ($notes)
            <p class="prompt-notes" lang="{{ $session->toLang->code }}">{{ $notes }}</p>
        @endif
        @if ($examples)
            <p class="examples" lang="{{ $session->toLang->code }}">{{ $examples }}</p>
        @endif

        <div class="buttons">
            {{-- Focused on load, so Enter moves on. --}}
            <a href="{{ route('learn.show', $session) }}" class="button" autofocus>
                {{ $result['cards_left'] === 0 ? 'See results' : 'Next' }}
            </a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.cards.edit', ['card' => $result['card_id'], 'session' => $session->id]) }}">Edit card</a>
            @endif
        </div>
    </div>
@endsection
