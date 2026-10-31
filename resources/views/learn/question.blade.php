@extends('layouts.app')

@section('title', 'Learn')

@section('content')
    @include('learn._header')

    <div class="card study">
        <p class="prompt" lang="{{ $session->fromLang->code }}">{{ $word }}</p>
        @if ($notes)
            <p class="prompt-notes" lang="{{ $session->fromLang->code }}">{{ $notes }}</p>
        @endif
        @if ($examples)
            <p class="examples" lang="{{ $session->fromLang->code }}">{{ $examples }}</p>
        @endif

        <form method="POST" action="{{ route('learn.answer', $session) }}" class="form answer-form">
            @csrf
            <input type="hidden" name="card_id" value="{{ $cardId }}">

            <label for="answer">{{ $session->toLang->name }}</label>
            <input id="answer" name="answer" required autofocus maxlength="255" lang="{{ $session->toLang->code }}"
                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
            @error('answer') <p class="error">{{ $message }}</p> @enderror

            <div class="buttons">
                <button type="submit" class="button">Submit</button>
                <button type="submit" name="dont_know" value="1" formnovalidate class="button secondary">I don’t know</button>
            </div>
        </form>
    </div>
@endsection
