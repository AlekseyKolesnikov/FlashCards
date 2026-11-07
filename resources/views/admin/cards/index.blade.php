@extends('layouts.app')

@section('title', 'Cards')

@section('content')
    @php
        // Header link that sorts by the column, toggling the direction when it is already sorted by it.
        $sortLink = function (string $column, string $label) use ($sort, $direction) {
            $dir = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
            $arrow = $sort === $column ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
            $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => null]);

            return '<a href="'.e($url).'">'.e($label).$arrow.'</a>';
        };
        $filtered = $filters['pair'] || $filters['complexity'] || $filters['q'] !== '';
    @endphp

    <div class="page-head">
        <h1>Cards</h1>
        <a href="{{ route('admin.cards.create') }}" class="button">Add card</a>
    </div>

    <form method="GET" action="{{ route('admin.cards.index') }}" class="filters">
        <select name="pair" aria-label="Language pair">
            <option value="">All language pairs</option>
            @foreach ($pairs as $pair)
                <option value="{{ $pair['key'] }}" @selected($filters['pair'] === $pair['key'])>{{ $pair['label'] }}</option>
            @endforeach
        </select>
        <select name="complexity" aria-label="Complexity">
            <option value="">Any complexity</option>
            @for ($i = config('flashcards.complexity.min'); $i <= config('flashcards.complexity.max'); $i++)
                <option value="{{ $i }}" @selected($filters['complexity'] === $i)>Complexity {{ $i }}</option>
            @endfor
        </select>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search words" aria-label="Search words">
        <label class="filter-check">
            <input type="checkbox" name="notes" value="1" @checked($filters['notes'])> Search notes
        </label>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $direction }}">
        <button type="submit" class="button secondary">Filter</button>
        @if ($filtered)
            <a href="{{ route('admin.cards.index') }}">Clear</a>
        @endif
    </form>

    @if ($cards->isEmpty())
        <p class="muted">
            @if ($filtered)
                No cards match these filters.
            @else
                No cards yet. <a href="{{ route('admin.cards.create') }}">Add a card</a>
                or <a href="{{ route('admin.import.index') }}">import a file</a>.
            @endif
        </p>
    @else
        <p class="muted">{{ $cards->total() }} card(s)</p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="check"><input type="checkbox" data-select-all="bulk" aria-label="Select all"></th>
                        <th>{!! $sortLink('lang1', 'Lang 1') !!}</th>
                        <th>{!! $sortLink('word1', 'Word 1') !!}</th>
                        <th>{!! $sortLink('lang2', 'Lang 2') !!}</th>
                        <th>{!! $sortLink('word2', 'Word 2') !!}</th>
                        <th>{!! $sortLink('complexity', 'Complexity') !!}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cards as $card)
                        <tr>
                            <td class="check">
                                <input type="checkbox" name="ids[]" value="{{ $card->id }}" form="bulk"
                                       aria-label="Select {{ $card->word1 }}">
                            </td>
                            <td title="{{ $card->lang1->name }}">{{ $card->lang1->code }}</td>
                            <td>{{ $card->word1 }}</td>
                            <td title="{{ $card->lang2->name }}">{{ $card->lang2->code }}</td>
                            <td>{{ $card->word2 }}</td>
                            <td>{{ $card->complexity }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.cards.edit', $card) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.cards.destroy', $card) }}"
                                      data-confirm="Delete the card “{{ $card->word1 }}” – “{{ $card->word2 }}”? This cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Both buttons act on the ticked rows; each button chooses the HTTP method. --}}
        <form id="bulk" method="POST" action="{{ route('admin.cards.destroy-many') }}" class="filters" data-require-selected>
            @csrf
            <select name="complexity" aria-label="New complexity" required>
                <option value="">Complexity</option>
                @for ($i = config('flashcards.complexity.min'); $i <= config('flashcards.complexity.max'); $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
            <button type="submit" name="_method" value="PATCH" class="button secondary">Set complexity</button>
            <button type="submit" name="_method" value="DELETE" class="button danger" formnovalidate
                    data-confirm="Delete {count} selected card(s)? This cannot be undone.">Delete selected</button>
        </form>

        {{ $cards->links() }}
    @endif
@endsection
