<?php

namespace App\Services\Import;

/**
 * Outcome of checking an import file, before anything is saved.
 */
class ImportResult
{
    /** @var list<array<string, mixed>> Card attributes ready to insert. */
    public array $valid = [];

    /** @var list<array{row: int, word1: string, word2: string}> Rows that match a card already saved or earlier in the file. */
    public array $duplicates = [];

    /**
     * Rows that match a saved card but set some of its fields differently. Words and
     * changes are in the saved card's language order; changes are column => [old, new].
     *
     * @var list<array{row: int, id: int, word1: string, word2: string, changes: array<string, array{0: mixed, 1: mixed}>}>
     */
    public array $updates = [];

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];

    /**
     * Language pairs of the file's valid rows, keyed "3-7" (lower language ID first), with the
     * number of cards already saved for each.
     *
     * @var array<string, array{label: string, saved: int}>
     */
    public array $pairs = [];

    /** @var array<string, int> Pairs whose saved cards are deleted before importing, with the number of cards. */
    public array $replaced = [];
}
