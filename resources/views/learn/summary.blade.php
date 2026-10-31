@extends('layouts.app')

@section('title', 'Session complete')

@section('content')
    @include('learn._header')

    <div class="card study">
        <h1>Session complete</h1>
        <p><strong>{{ $firstTry }}</strong> of <strong>{{ $total }}</strong> {{ $total === 1 ? 'card' : 'cards' }}
            answered correctly on the first try.</p>

        @if ($mistakes->isNotEmpty())
            <h2>Words to repeat</h2>
            <p class="muted">These come back in your next sessions.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ $session->fromLang->name }}</th>
                            <th>{{ $session->toLang->name }}</th>
                            <th>Wrong answers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mistakes as $entry)
                            <tr>
                                <td>{{ $entry->card->sideField('word', $session->from_lang_id) }}</td>
                                <td>{{ $entry->card->sideField('word', $session->to_lang_id) }}</td>
                                <td>{{ $entry->wrong_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="buttons">
            <form method="POST" action="{{ route('learn.start') }}">
                @csrf
                <input type="hidden" name="from_lang_id" value="{{ $session->from_lang_id }}">
                <input type="hidden" name="to_lang_id" value="{{ $session->to_lang_id }}">
                <button type="submit" class="button" autofocus>Start next session</button>
            </form>
            <a href="{{ route('learn.index') }}">Back to start page</a>
        </div>
    </div>
@endsection
