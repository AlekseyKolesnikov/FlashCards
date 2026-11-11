<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\Import\AnkiReader;
use App\Services\Import\CardImporter;
use App\Services\Import\CsvReader;
use App\Services\Import\ImportException;
use App\Services\Import\ImportResult;
use App\Services\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CSV and Anki import (SPECIFICATION.md §5.4–5.5): upload, (map Anki columns,) preview, confirm.
 * The uploaded file waits in storage/app/private/imports until the import is confirmed or cancelled.
 */
class ImportController extends Controller
{
    private const SESSION_KEY = 'import';

    /** Rows listed per table in the preview; the counts cover all of them. */
    private const PREVIEW_LIMIT = 200;

    public function __construct(
        private CsvReader $csv,
        private AnkiReader $anki,
        private CardImporter $importer,
    ) {}

    public function index(Vocabulary $vocabulary): JsonResponse
    {
        return response()->json([
            'languages' => Language::orderBy('name')->get(['id', 'code', 'name']),
            'pairs' => $vocabulary->languagePairs(),
            'csv_columns' => CsvReader::COLUMNS,
        ]);
    }

    public function uploadCsv(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'extensions:csv,txt', 'max:10240']]);

        $this->start($request, 'csv');

        return response()->json(['format' => 'csv']);
    }

    public function uploadAnki(Request $request): JsonResponse
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

        return response()->json(['format' => 'anki']);
    }

    public function mapping(): JsonResponse
    {
        $pending = $this->pending('anki');

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

        return response()->json([
            'name' => $pending['name'],
            'lang1' => $pending['options']['lang1'],
            'lang2' => $pending['options']['lang2'],
            'sample' => $sample,
            'column_names' => $file['columns'],
            'column_count' => max(array_map('count', $sample) ?: [0]),
            'mapping' => $pending['options']['mapping'] ?? ['word1', 'word2'],
            'fields' => AnkiReader::FIELDS,
        ]);
    }

    public function saveMapping(Request $request): JsonResponse
    {
        $pending = $this->pending('anki');

        $mapping = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['required', Rule::in([...AnkiReader::FIELDS, 'ignore'])],
        ])['mapping'];

        $used = array_values(array_filter($mapping, fn ($field) => $field !== 'ignore'));
        if (! in_array('word1', $used, true) || ! in_array('word2', $used, true)) {
            throw ValidationException::withMessages(['mapping' => 'Choose a column for word 1 and for word 2.']);
        }
        if (count($used) !== count(array_unique($used))) {
            throw ValidationException::withMessages(['mapping' => 'Each card field can be chosen for one column only.']);
        }

        $pending['options']['mapping'] = array_map('strval', $mapping);
        $request->session()->put(self::SESSION_KEY, $pending);

        return response()->json(['format' => 'anki']);
    }

    public function preview(Request $request): JsonResponse
    {
        $pending = $this->pending();

        try {
            $result = $this->importer->check($this->rows($pending), $this->replace($request));
        } catch (ImportException $e) {
            return $this->fail($e);
        }

        return response()->json($this->previewData($pending, $result));
    }

    public function store(Request $request): JsonResponse
    {
        $pending = $this->pending();

        try {
            // Checked again: the vocabulary may have changed since the preview.
            $result = $this->importer->check($this->rows($pending), $this->replace($request));
        } catch (ImportException $e) {
            return $this->fail($e);
        }

        $update = $request->boolean('update');
        $counts = $this->importer->import($result, $update);
        $this->clear();

        $status = sprintf('Imported %d card(s)', $counts['imported']);
        if ($counts['deleted']) {
            $labels = array_map(fn (string $pair) => $result->pairs[$pair]['label'], array_keys($result->replaced));
            $status = sprintf('Deleted %d existing card(s) of %s. %s', $counts['deleted'], implode(', ', $labels), $status);
        }
        if ($update) {
            $status .= sprintf(', updated %d existing card(s)', $counts['updated']);
        }
        $skipped = count($result->duplicates) + ($update ? 0 : count($result->updates));

        return response()->json(['message' => sprintf(
            '%s. Skipped %d duplicate(s) and %d row(s) with errors.', $status, $skipped, count($result->errors),
        )]);
    }

    public function cancel(): JsonResponse
    {
        $this->clear();

        return response()->json(['message' => 'Import cancelled.']);
    }

    /**
     * @param  array{format: string, name: string}  $pending
     * @return array<string, mixed>
     */
    private function previewData(array $pending, ImportResult $result): array
    {
        $limit = fn (array $rows) => array_slice($rows, 0, self::PREVIEW_LIMIT);
        $pairs = [];
        foreach ($result->pairs as $key => $pair) {
            $pairs[] = ['key' => $key] + $pair;
        }

        return [
            'name' => $pending['name'],
            'format' => $pending['format'],
            'valid_count' => count($result->valid),
            'errors' => $limit($result->errors),
            'error_count' => count($result->errors),
            'duplicates' => $limit($result->duplicates),
            'duplicate_count' => count($result->duplicates),
            'updates' => array_map(fn (array $update) => ['changes' => (object) $update['changes']] + $update, $limit($result->updates)),
            'update_count' => count($result->updates),
            'pairs' => $pairs,
            'replaced' => array_keys($result->replaced),
            'delete_count' => array_sum($result->replaced),
        ];
    }

    /**
     * Language pair keys whose saved cards the admin wants replaced by the file's cards.
     *
     * @return list<string>
     */
    private function replace(Request $request): array
    {
        return array_values($request->validate([
            'replace' => ['array'],
            'replace.*' => ['string', 'regex:/^\d+-\d+$/'],
        ])['replace'] ?? []);
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
     * The import in progress. Answers 404 when there is none (or not of the given format).
     *
     * @return array{format: string, path: string, name: string, options: array<string, mixed>}
     */
    private function pending(?string $format = null): array
    {
        $pending = session(self::SESSION_KEY);

        abort_unless($pending && Storage::exists($pending['path']) && (! $format || $pending['format'] === $format),
            404, 'There is no import in progress.');

        return $pending;
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

    /**
     * A file that cannot be read ends the import.
     */
    private function fail(ImportException $e): JsonResponse
    {
        $this->clear();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    private function clear(): void
    {
        $pending = session()->pull(self::SESSION_KEY);

        if ($pending) {
            Storage::delete($pending['path']);
        }
    }
}
