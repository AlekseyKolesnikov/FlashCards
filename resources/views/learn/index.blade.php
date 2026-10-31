@extends('layouts.app')

@section('title', 'Learn')

@section('content')
    <h1>Learn</h1>

    @if ($directions === [])
        <p class="muted">There are no cards to learn yet.</p>
    @else
        <p class="muted">Choose what to translate from and to.</p>

        <div class="directions">
            @foreach ($directions as $direction)
                <form method="POST" action="{{ route('learn.start') }}" class="card direction">
                    @csrf
                    <input type="hidden" name="from_lang_id" value="{{ $direction['from']->id }}">
                    <input type="hidden" name="to_lang_id" value="{{ $direction['to']->id }}">

                    <h2>{{ $direction['from']->name }} → {{ $direction['to']->name }}</h2>
                    <p class="counts">
                        <span><strong>{{ $direction['new'] }}</strong> new</span>
                        <span><strong>{{ $direction['active'] }}</strong> learning</span>
                        <span><strong>{{ $direction['learned'] }}</strong> learned</span>
                    </p>

                    @if ($direction['cards_left'] !== null)
                        <button type="submit" class="button">Resume ({{ $direction['cards_left'] }} left)</button>
                    @else
                        <button type="submit" class="button secondary">Start session</button>
                    @endif
                </form>
            @endforeach
        </div>
    @endif
@endsection
