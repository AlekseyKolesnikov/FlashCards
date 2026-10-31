<?php

namespace App\Services;

use Normalizer;

/**
 * Compares a typed answer with the stored word (SPECIFICATION.md §7).
 */
class AnswerChecker
{
    /**
     * Normalize a word: Unicode NFC, lower case, drop dots and commas,
     * trim and collapse whitespace. Everything else must match exactly.
     */
    public function normalize(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['.', ','], '', $text);

        // \p{Z} also covers non-breaking and other Unicode spaces.
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text), ' ');
    }

    public function matches(string $typed, string $expected): bool
    {
        return $this->normalize($typed) === $this->normalize($expected);
    }
}
