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
