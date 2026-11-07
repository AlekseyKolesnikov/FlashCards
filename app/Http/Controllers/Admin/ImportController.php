<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\Import\AnkiReader;
use App\Services\Import\CardImporter;
use App\Services\Import\CsvReader;
use App\Services\Import\ImportException;
use App\Services\Vocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CSV and Anki import (SPECIFICATION.md §5.4–5.5): upload, (map Anki columns,) preview, confirm.
 * The uploaded file waits in storage/app/private/imports until the import is confirmed or cancelled.
 */
class ImportController extends Controller
{
    private const SESSION_KEY = 'import';

    public function __construct(
        private CsvReader $csv,
        private AnkiReader $anki,
        private CardImporter $importer,
    ) {}

    public function index(Vocabulary $vocabulary): View
    {
        return view('admin.import.index', [
            'languages' => Language::orderBy('name')->get(),
            'pairs' => $vocabulary->languagePairs(),
        ]);
    }

    public function uploadCsv(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'extensions:csv,txt', 'max:10240']]);

        $this->start($request, 'csv');

        return redirect()->route('admin.import.preview');
    }

    public function uploadAnki(Request $request): RedirectResponse
    {
        $complexity = config('flashcards.complexity');

        $options = $request->validate([
            'file' => ['required', 'file', 'extensions:txt,csv,tsv', 'max:10240'],
            'lang1_id' => ['required', 'integer', 'exists:languages,id'],
            'lang2_id' => ['required', 'integer', 'exists:languages,id', 'different:lang1_id'],
            'complexity' => ['required', 'integer', "between:{$complexity['min']},{$complexity['max']}"],
        ]);

        $this->start($request, 'anki', [
            'lang1' => Language::find($options['lang1_id'])->code,
            'lang2' => Language::find($options['lang2_id'])->code,
            'complexity' => (int) $options['complexity'],
            'mapping' => null,
        ]);

        return redirect()->route('admin.import.mapping');
    }

    public function mapping(): View|RedirectResponse
    {
        $pending = $this->pending();
        if (! $pending || $pending['format'] !== 'anki') {
            return redirect()->route('admin.import.index');
        }

        try {
            $file = $this->anki->read(Storage::path($pending['path']));
        } catch (ImportException $e) {
            return $this->fail($e);
        }

        // Shown as it will be imported, i.e. without HTML.
        $sample = array_map(
            fn (array $fields) => array_map(fn (string $value) => $this->anki->fieldText($value, $file['html']), $fields),
            array_slice($file['records'], 0, 5),
        );
        $columnCount = max(array_map('count', $sample));

        return view('admin.import.mapping', [
            'pending' => $pending,
            'sample' => $sample,
            'columnNames' => $file['columns'],
            'columnCount' => $columnCount,
            'mapping' => $pending['options']['mapping'] ?? [0 => 'word1', 1 => 'word2'],
            'fields' => AnkiReader::FIELDS,
        ]);
    }

    public function saveMapping(Request $request): RedirectResponse
    {
        $pending = $this->pending();
        if (! $pending || $pending['format'] !== 'anki') {
            return redirect()->route('admin.import.index');
        }

        $mapping = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['required', Rule::in([...AnkiReader::FIELDS, 'ignore'])],
        ])['mapping'];

        $used = array_values(array_filter($mapping, fn ($field) => $field !== 'ignore'));
        if (! in_array('word1', $used, true) || ! in_array('word2', $used, true)) {
            return back()->withErrors(['mapping' => 'Choose a column for word 1 and for word 2.']);
        }
        if (count($used) !== count(array_unique($used))) {
            return back()->withErrors(['mapping' => 'Each card field can be chosen for one column only.']);
        }

        $pending['options']['mapping'] = array_map('strval', $mapping);
        $request->session()->put(self::SESSION_KEY, $pending);

        return redirect()->route('admin.import.preview');
    }

    public function preview(): View|RedirectResponse
    {
        $pending = $this->pending();
        if (! $pending) {
            return redirect()->route('admin.import.index');
        }

        try {
            $result = $this->importer->check($this->rows($pending));
        } catch (ImportException $e) {
            return $this->fail($e);
        }

        return view('admin.import.preview', ['pending' => $pending, 'result' => $result]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->pending();
        if (! $pending) {
            return redirect()->route('admin.import.index');
        }

        try {
            // Checked again: the vocabulary may have changed since the preview.
            $result = $this->importer->check($this->rows($pending));
        } catch (ImportException $e) {
            return $this->fail($e);
        }

        $update = $request->boolean('update');
        $counts = $this->importer->import($result, $update);
        $this->clear();

        $status = sprintf('Imported %d card(s)', $counts['imported']);
        if ($update) {
            $status .= sprintf(', updated %d existing card(s)', $counts['updated']);
        }
        $skipped = count($result->duplicates) + ($update ? 0 : count($result->updates));

        return redirect()->route('admin.cards.index')->with('status', sprintf(
            '%s. Skipped %d duplicate(s) and %d row(s) with errors.', $status, $skipped, count($result->errors),
        ));
    }

    public function cancel(): RedirectResponse
    {
        $this->clear();

        return redirect()->route('admin.import.index')->with('status', 'Import cancelled.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function start(Request $request, string $format, array $options = []): void
    {
        $this->clear();

        $request->session()->put(self::SESSION_KEY, [
            'format' => $format,
            'path' => $request->file('file')->store('imports'),
            'name' => $request->file('file')->getClientOriginalName(),
            'options' => $options,
        ]);
    }

    /**
     * @return array{format: string, path: string, name: string, options: array<string, mixed>}|null
     */
    private function pending(): ?array
    {
        $pending = session(self::SESSION_KEY);

        return $pending && Storage::exists($pending['path']) ? $pending : null;
    }

    /**
     * @param  array{format: string, path: string, options: array<string, mixed>}  $pending
     * @return array<int, array<string, string>>
     */
    private function rows(array $pending): array
    {
        $path = Storage::path($pending['path']);

        if ($pending['format'] === 'csv') {
            return $this->csv->read($path);
        }

        $options = $pending['options'];
        if ($options['mapping'] === null) {
            throw new ImportException('Choose which column holds which card field first.');
        }

        return $this->anki->toRows($this->anki->read($path), $options['mapping'], $options['lang1'], $options['lang2'], $options['complexity']);
    }

    private function fail(ImportException $e): RedirectResponse
    {
        $this->clear();

        return redirect()->route('admin.import.index')->with('error', $e->getMessage());
    }

    private function clear(): void
    {
        $pending = session()->pull(self::SESSION_KEY);

        if ($pending) {
            Storage::delete($pending['path']);
        }
    }
}
