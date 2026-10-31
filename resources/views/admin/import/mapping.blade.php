@extends('layouts.app')

@section('title', 'Choose columns')

@section('content')
    @php
        $labels = [
            'word1' => "Word 1 ({$pending['options']['lang1']})",
            'notes1' => "Notes 1 ({$pending['options']['lang1']})",
            'examples1' => "Examples 1 ({$pending['options']['lang1']})",
            'word2' => "Word 2 ({$pending['options']['lang2']})",
            'notes2' => "Notes 2 ({$pending['options']['lang2']})",
            'examples2' => "Examples 2 ({$pending['options']['lang2']})",
        ];
    @endphp

    <h1>Choose columns</h1>
    <p class="muted">{{ $pending['name'] }} — the first {{ count($sample) }} notes are shown.
        Pick the card field for each column; word 1 and word 2 are required.</p>

    @error('mapping') <p class="notice danger">{{ $message }}</p> @enderror

    <form method="POST" action="{{ route('admin.import.mapping') }}">
        @csrf
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        @for ($column = 0; $column < $columnCount; $column++)
                            <th>
                                @if ($columnNames[$column] ?? null)
                                    <div class="muted">{{ $columnNames[$column] }}</div>
                                @endif
                                <select name="mapping[{{ $column }}]" aria-label="Column {{ $column + 1 }}">
                                    <option value="ignore">Ignore</option>
                                    @foreach ($fields as $field)
                                        <option value="{{ $field }}"
                                            @selected(old("mapping.{$column}", $mapping[$column] ?? 'ignore') === $field)>
                                            {{ $labels[$field] }}
                                        </option>
                                    @endforeach
                                </select>
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sample as $fields)
                        <tr>
                            @for ($column = 0; $column < $columnCount; $column++)
                                <td class="sample">{{ \Illuminate\Support\Str::limit($fields[$column] ?? '', 120) }}</td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="buttons">
            <button type="submit" class="button">Next: check file</button>
            <button type="submit" form="cancel-import" class="link-button">Cancel import</button>
        </div>
    </form>

    <form id="cancel-import" method="POST" action="{{ route('admin.import.cancel') }}">
        @csrf
        @method('DELETE')
    </form>
@endsection
