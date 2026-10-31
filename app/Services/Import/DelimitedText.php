<?php

namespace App\Services\Import;

/**
 * Helpers shared by the CSV and Anki readers.
 */
class DelimitedText
{
    /**
     * Read a file as UTF-8 text without a BOM.
     */
    public static function load(string $path): string
    {
        $content = (string) file_get_contents($path);

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw new ImportException('The file is not UTF-8 text. In Excel, use "Save as" → "CSV UTF-8".');
        }

        return str_replace("\r\n", "\n", $content);
    }

    /**
     * Split text into records. Quoted fields (RFC 4180) may contain delimiters and line breaks.
     * Blank lines are skipped; keys are 1-based record numbers.
     *
     * @return array<int, list<string>>
     */
    public static function parse(string $content, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $records = [];
        $number = 0;

        while (($fields = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $number++;

            if ($fields === [null] || implode('', $fields) === '') {
                continue;
            }

            $records[$number] = array_map(fn ($field) => (string) $field, $fields);
        }

        fclose($stream);

        return $records;
    }
}
