@extends('layouts.app')

@section('title', 'Import / export')

@section('content')
    @php
        $complexity = config('flashcards.complexity');
    @endphp

    <h1>Import / export</h1>

    <div class="panels">
        <form method="POST" action="{{ route('admin.import.csv') }}" enctype="multipart/form-data" class="card form">
            @csrf
            <h2>Import CSV</h2>
            <p class="hint">UTF-8, comma, semicolon or tab separated. The first row names the columns:</p>
            <pre class="code">{{ implode(',', \App\Services\Import\CsvReader::COLUMNS) }}</pre>
            <p class="hint">
                <code>lang1</code>, <code>word1</code>, <code>lang2</code> and <code>word2</code> are required.
                Languages are codes from the <a href="{{ route('admin.languages.index') }}">language list</a>.
                Empty complexity means {{ $complexity['default'] }}.
            </p>

            <label for="csv-file">File</label>
            <input id="csv-file" name="file" type="file" accept=".csv,.txt" required>
            @error('file') <p class="error">{{ $message }}</p> @enderror

            <button type="submit" class="button">Check file</button>
        </form>

        <form method="POST" action="{{ route('admin.import.anki') }}" enctype="multipart/form-data" class="card form">
            @csrf
            <h2>Import from Anki</h2>
            <p class="hint">In Anki, use File → Export → “Notes in Plain Text (.txt)”.
                You choose which column is which on the next page.</p>

            <label for="anki-file">File</label>
            <input id="anki-file" name="file" type="file" accept=".txt,.csv,.tsv" required>

            <div class="row">
                @foreach ([1, 2] as $side)
                    <div class="grow">
                        <label for="anki-lang{{ $side }}">Language {{ $side }}</label>
                        <select id="anki-lang{{ $side }}" name="lang{{ $side }}_id" required>
                            <option value="">Choose…</option>
                            @foreach ($languages as $language)
                                <option value="{{ $language->id }}" @selected((int) old("lang{$side}_id") === $language->id)>
                                    {{ $language->name }} ({{ $language->code }})
                                </option>
                            @endforeach
                        </select>
                        @error("lang{$side}_id") <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <label for="anki-complexity">Complexity for all cards</label>
            <select id="anki-complexity" name="complexity" class="short">
                @for ($i = $complexity['min']; $i <= $complexity['max']; $i++)
                    <option value="{{ $i }}" @selected((int) old('complexity', $complexity['default']) === $i)>{{ $i }}</option>
                @endfor
            </select>

            <button type="submit" class="button">Next: choose columns</button>
        </form>

        <form method="GET" action="{{ route('admin.export') }}" class="card form">
            <h2>Export</h2>

            <label for="export-pair">Cards</label>
            <select id="export-pair" name="pair">
                <option value="">All language pairs</option>
                @foreach ($pairs as $pair)
                    <option value="{{ $pair['key'] }}">{{ $pair['label'] }}</option>
                @endforeach
            </select>

            <label for="export-format">Format</label>
            <select id="export-format" name="format">
                <option value="csv">CSV (opens in Excel, can be imported here)</option>
                <option value="anki">Anki plain text (.txt)</option>
            </select>

            <button type="submit" class="button">Download</button>
        </form>
    </div>
@endsection
