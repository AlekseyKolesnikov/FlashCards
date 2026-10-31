<?php

namespace App\Services\Import;

/**
 * Outcome of checking an import file, before anything is saved.
 */
class ImportResult
{
    /** @var list<array<string, mixed>> Card attributes ready to insert. */
    public array $valid = [];

    /** @var list<array{row: int, word1: string, word2: string}> */
    public array $duplicates = [];

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];
}
