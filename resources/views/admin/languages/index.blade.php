@extends('layouts.app')

@section('title', 'Languages')

@section('content')
    <h1>Languages</h1>

    @if ($languages->isEmpty())
        <p class="muted">No languages yet.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Cards</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($languages as $language)
                        <tr>
                            <td>{{ $language->code }}</td>
                            <td>{{ $language->name }}</td>
                            <td>{{ $language->cards_count }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.languages.edit', $language) }}">Edit</a>
                                @if ((int) $language->cards_count === 0)
                                    <form method="POST" action="{{ route('admin.languages.destroy', $language) }}"
                                          data-confirm="Delete {{ $language->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-button danger">Delete</button>
                                    </form>
                                @else
                                    <span class="muted" title="Languages used by cards cannot be deleted">In use</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($complexities)
        <h2>Words by complexity</h2>
        <div class="table-wrap">
            <table class="table stats">
                <thead>
                    <tr>
                        <th>Language</th>
                        @foreach ($complexities as $complexity)
                            <th class="num">{{ $complexity }}</th>
                        @endforeach
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($languages as $language)
                        @continue(! isset($byLanguage[$language->id]))
                        <tr>
                            <td>{{ $language->name }}</td>
                            @foreach ($complexities as $complexity)
                                <td class="num">{{ $byLanguage[$language->id][$complexity] ?? '' }}</td>
                            @endforeach
                            <td class="num">{{ array_sum($byLanguage[$language->id]) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>All cards</td>
                        @foreach ($complexities as $complexity)
                            <td class="num">
                                <a href="{{ route('admin.cards.index', ['complexity' => $complexity]) }}">{{ $totals[$complexity] }}</a>
                            </td>
                        @endforeach
                        <td class="num">{{ array_sum($totals) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="hint">Each card has two languages, so it is counted in both language rows.</p>
    @endif

    <form method="POST" action="{{ route('admin.languages.store') }}" class="card form inline-form">
        @csrf
        <h2>Add a language</h2>
        <div class="row">
            <div>
                <label for="code">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" required maxlength="10" placeholder="de">
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grow">
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="64" placeholder="German">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="button">Add</button>
        </div>
        <p class="hint">The code is used in CSV files, e.g. <code>en</code>, <code>de</code>, <code>pt-br</code>.</p>
    </form>
@endsection
