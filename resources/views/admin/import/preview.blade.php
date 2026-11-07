@extends('layouts.app')

@section('title', 'Check import')

@section('content')
    @php
        $limit = 200;
        $rowLabel = $pending['format'] === 'anki' ? 'Note' : 'Row';
        $validCount = count($result->valid);
        $updateCount = count($result->updates);
        $fieldNames = [
            'complexity' => 'Complexity',
            'notes1' => 'Notes 1', 'examples1' => 'Examples 1',
            'notes2' => 'Notes 2', 'examples2' => 'Examples 2',
        ];
    @endphp

    <h1>Check import</h1>
    <p class="muted">{{ $pending['name'] }}</p>

    <ul class="summary">
        <li><strong>{{ $validCount }}</strong> card(s) will be imported.</li>
        @if ($updateCount)
            <li><strong>{{ $updateCount }}</strong> existing card(s) differ from the file. They are skipped unless you choose to update them.</li>
        @endif
        <li><strong>{{ count($result->duplicates) }}</strong> duplicate(s) will be skipped.</li>
        <li><strong>{{ count($result->errors) }}</strong> row(s) with errors will be skipped.</li>
    </ul>

    <div class="buttons">
        <form method="POST" action="{{ route('admin.import.store') }}">
            @csrf
            <button type="submit" class="button" @disabled($validCount === 0)>Import {{ $validCount }} card(s)</button>
        </form>
        @if ($updateCount)
            <form method="POST" action="{{ route('admin.import.store') }}">
                @csrf
                <input type="hidden" name="update" value="1">
                <button type="submit" class="button secondary">
                    {{ $validCount ? "Import {$validCount} card(s) and update" : 'Update' }} {{ $updateCount }} existing card(s)
                </button>
            </form>
        @endif
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

    @if ($result->updates)
        <h2>Changes to existing cards</h2>
        <p class="muted">Saved only if you choose to update existing cards. Words are in the existing card's language order.</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ $rowLabel }}</th><th>Word 1</th><th>Word 2</th><th>Changes</th></tr></thead>
                <tbody>
                    @foreach (array_slice($result->updates, 0, $limit) as $update)
                        <tr>
                            <td>{{ $update['row'] }}</td>
                            <td>{{ $update['word1'] }}</td>
                            <td>{{ $update['word2'] }}</td>
                            <td>
                                @foreach ($update['changes'] as $field => [$old, $new])
                                    <div>{{ $fieldNames[$field] }}: {{ Str::limit((string) ($old ?? '—'), 60) }} → {{ Str::limit((string) ($new ?? '—'), 60) }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($updateCount > $limit)
            <p class="muted">…and {{ $updateCount - $limit }} more.</p>
        @endif
    @endif

    @if ($result->duplicates)
        <h2>Duplicates</h2>
        <p class="muted">These cards already exist with the same values, or appear earlier in the file.</p>
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
