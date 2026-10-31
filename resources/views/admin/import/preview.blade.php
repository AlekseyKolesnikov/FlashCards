@extends('layouts.app')

@section('title', 'Check import')

@section('content')
    @php
        $limit = 200;
        $rowLabel = $pending['format'] === 'anki' ? 'Note' : 'Row';
        $validCount = count($result->valid);
    @endphp

    <h1>Check import</h1>
    <p class="muted">{{ $pending['name'] }}</p>

    <ul class="summary">
        <li><strong>{{ $validCount }}</strong> card(s) will be imported.</li>
        <li><strong>{{ count($result->duplicates) }}</strong> duplicate(s) will be skipped.</li>
        <li><strong>{{ count($result->errors) }}</strong> row(s) with errors will be skipped.</li>
    </ul>

    <div class="buttons">
        <form method="POST" action="{{ route('admin.import.store') }}">
            @csrf
            <button type="submit" class="button" @disabled($validCount === 0)>Import {{ $validCount }} card(s)</button>
        </form>
        @if ($pending['format'] === 'anki')
            <a href="{{ route('admin.import.mapping') }}">Change columns</a>
        @endif
        <form method="POST" action="{{ route('admin.import.cancel') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="link-button">Cancel</button>
        </form>
    </div>

    @if ($result->errors)
        <h2>Errors</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ $rowLabel }}</th><th>Problem</th></tr></thead>
                <tbody>
                    @foreach (array_slice($result->errors, 0, $limit) as $error)
                        <tr><td>{{ $error['row'] }}</td><td>{{ $error['message'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($result->errors) > $limit)
            <p class="muted">…and {{ count($result->errors) - $limit }} more.</p>
        @endif
    @endif

    @if ($result->duplicates)
        <h2>Duplicates</h2>
        <p class="muted">These cards already exist, or appear earlier in the file.</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ $rowLabel }}</th><th>Word 1</th><th>Word 2</th></tr></thead>
                <tbody>
                    @foreach (array_slice($result->duplicates, 0, $limit) as $duplicate)
                        <tr><td>{{ $duplicate['row'] }}</td><td>{{ $duplicate['word1'] }}</td><td>{{ $duplicate['word2'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($result->duplicates) > $limit)
            <p class="muted">…and {{ count($result->duplicates) - $limit }} more.</p>
        @endif
    @endif
@endsection
