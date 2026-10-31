<?php

namespace App\Services\Import;

/**
 * Reads the CSV format from SPECIFICATION.md §5.4.
 */
class CsvReader
{
    public const COLUMNS = ['lang1', 'word1', 'notes1', 'examples1', 'lang2', 'word2', 'notes2', 'examples2', 'complexity'];

    private const REQUIRED = ['lang1', 'word1', 'lang2', 'word2'];

    /**
     * @return array<int, array<string, string>> Card fields keyed by row number (the header is row 1).
     */
    public function read(string $path): array
    {
        $content = DelimitedText::load($path);
        $firstLine = strtok($content, "\r\n") ?: '';
        $records = DelimitedText::parse($content, $this->detectDelimiter($firstLine));

        if ($records === []) {
            throw new ImportException('The file is empty.');
        }

        $headerRow = array_key_first($records);
        $header = array_map(fn ($name) => strtolower(trim($name)), $records[$headerRow]);
        unset($records[$headerRow]);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            throw new ImportException('Missing column(s) in the header row: '.implode(', ', $missing).'.');
        }

        $positions = array_intersect($header, self::COLUMNS);
        $rows = [];

        foreach ($records as $number => $fields) {
            $row = [];
            foreach ($positions as $index => $column) {
                $row[$column] = trim($fields[$index] ?? '');
            }
            $rows[$number - $headerRow + 1] = $row;
        }

        return $rows;
    }

    /**
     * Excel saves CSV with commas or semicolons depending on the locale, so pick
     * whichever delimiter the header row uses most.
     */
    private function detectDelimiter(string $headerLine): string
    {
        $counts = [',' => 0, ';' => 0, "\t" => 0];
        foreach (array_keys($counts) as $delimiter) {
            $counts[$delimiter] = substr_count($headerLine, $delimiter);
        }
        arsort($counts);

        return array_key_first($counts);
    }
}
