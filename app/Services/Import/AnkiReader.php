<?php

namespace App\Services\Import;

/**
 * Reads Anki's "Notes in Plain Text" export (SPECIFICATION.md §5.5).
 */
class AnkiReader
{
    /** Card fields a column can be mapped to. */
    public const FIELDS = ['word1', 'notes1', 'examples1', 'word2', 'notes2', 'examples2'];

    private const SEPARATORS = [
        'comma' => ',', 'semicolon' => ';', 'tab' => "\t", 'space' => ' ', 'pipe' => '|', 'colon' => ':',
    ];

    /**
     * @return array{columns: list<string>|null, html: bool, records: array<int, list<string>>}
     */
    public function read(string $path): array
    {
        $content = DelimitedText::load($path);
        $headers = [];

        // Anki puts "#key:value" header lines at the top of the file.
        while (str_starts_with($content, '#')) {
            $end = strcspn($content, "\r\n");
            $line = substr($content, 1, $end - 1);
            $content = ltrim(substr($content, $end), "\r\n");

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = $value;
            }
        }

        $separator = $this->separator($headers['separator'] ?? null);
        $records = DelimitedText::parse($content, $separator);

        if ($records === []) {
            throw new ImportException('The file has no notes.');
        }

        return [
            'columns' => isset($headers['columns']) ? explode($separator, rtrim($headers['columns'], "\r\n")) : null,
            'html' => strtolower(trim($headers['html'] ?? 'true')) !== 'false',
            'records' => $records,
        ];
    }

    /**
     * Turn records into card fields, using the admin's column mapping.
     *
     * @param  array{html: bool, records: array<int, list<string>>}  $file
     * @param  array<int, string>  $mapping  Column index => card field or "ignore".
     * @return array<int, array<string, string>> Card fields keyed by note number.
     */
    public function toRows(array $file, array $mapping, string $lang1, string $lang2, int $complexity): array
    {
        $rows = [];

        foreach (array_values($file['records']) as $index => $fields) {
            $row = ['lang1' => $lang1, 'lang2' => $lang2, 'complexity' => (string) $complexity];

            foreach ($mapping as $column => $field) {
                if (in_array($field, self::FIELDS, true)) {
                    $row[$field] = $this->fieldText($fields[$column] ?? '', $file['html']);
                }
            }

            $rows[$index + 1] = $row;
        }

        return $rows;
    }

    private function separator(?string $value): string
    {
        if ($value === null) {
            return "\t";
        }

        $name = strtolower(trim($value));

        return self::SEPARATORS[$name] ?? (mb_strlen($value) === 1 ? $value : "\t");
    }

    /**
     * A field as it will be imported: HTML stripped (if the file uses HTML) and trimmed.
     */
    public function fieldText(string $value, bool $html): string
    {
        if ($html) {
            $value = preg_replace('#<br\s*/?>|</div>|</p>#i', "\n", $value);
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = preg_replace("/\n{3,}/", "\n\n", $value);
        }

        return trim($value);
    }
}
