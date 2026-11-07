@extends('layouts.app')

@section('title', $card->exists ? 'Edit card' : 'Add card')

@section('content')
    <form method="POST" class="card form"
          action="{{ $card->exists ? route('admin.cards.update', $card) : route('admin.cards.store') }}">
        @csrf
        @if ($card->exists)
            @method('PUT')
        @endif

        <h1>{{ $card->exists ? 'Edit card' : 'Add card' }}</h1>

        @if ($languages->count() < 2)
            <p class="notice warning">
                A card needs two languages. <a href="{{ route('admin.languages.index') }}">Add languages</a> first.
            </p>
        @endif

        <div class="sides">
            @foreach ([1, 2] as $side)
                <fieldset class="side">
                    <legend>Side {{ $side }}</legend>

                    <label for="lang{{ $side }}_id">Language</label>
                    <select id="lang{{ $side }}_id" name="lang{{ $side }}_id" required>
                        <option value="">Choose…</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language->id }}"
                                @selected((int) old("lang{$side}_id", $card->{"lang{$side}_id"}) === $language->id)>
                                {{ $language->name }} ({{ $language->code }})
                            </option>
                        @endforeach
                    </select>
                    @error("lang{$side}_id") <p class="error">{{ $message }}</p> @enderror

                    <label for="word{{ $side }}">Word</label>
                    <input id="word{{ $side }}" name="word{{ $side }}" required maxlength="255"
                           value="{{ old("word{$side}", $card->{"word{$side}"}) }}" @if ($side === 1) autofocus @endif>
                    <p class="hint">Exactly what learners must type. Put remarks in Notes, not in brackets here.</p>
                    @error("word{$side}") <p class="error">{{ $message }}</p> @enderror

                    <label for="notes{{ $side }}">Notes</label>
                    <input id="notes{{ $side }}" name="notes{{ $side }}" maxlength="255"
                           value="{{ old("notes{$side}", $card->{"notes{$side}"}) }}">
                    <p class="hint">Short remarks about this word, e.g. “female, plural”.</p>
                    @error("notes{$side}") <p class="error">{{ $message }}</p> @enderror

                    <label for="examples{{ $side }}">Examples</label>
                    <textarea id="examples{{ $side }}" name="examples{{ $side }}" rows="4"
                              maxlength="2000">{{ old("examples{$side}", $card->{"examples{$side}"}) }}</textarea>
                    @error("examples{$side}") <p class="error">{{ $message }}</p> @enderror
                </fieldset>
            @endforeach
        </div>

        <fieldset class="choices">
            <legend>Complexity</legend>
            <div class="choice-row">
                @for ($i = config('flashcards.complexity.min'); $i <= config('flashcards.complexity.max'); $i++)
                    <label>
                        <input type="radio" name="complexity" value="{{ $i }}" required
                               @checked((int) old('complexity', $card->complexity) === $i)>
                        <span>{{ $i }}</span>
                    </label>
                @endfor
            </div>
        </fieldset>
        <p class="hint">Lower values are learned first.</p>
        @error('complexity') <p class="error">{{ $message }}</p> @enderror

        @if ($card->exists)
            <p class="hint">Changing the card's languages resets learners' progress for it.</p>
        @endif

        <div class="buttons">
            <button type="submit" class="button">Save</button>
            @unless ($card->exists)
                <button type="submit" name="another" value="1" class="button secondary">Save and add another</button>
            @endunless
            <a href="{{ session('cards.list_url', route('admin.cards.index')) }}">Cancel</a>
        </div>
    </form>
@endsection
