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

    /**
     * $lang is the code of the answer's language. In Russian, ё and е count as
     * the same letter in either direction (§7.1). This is not part of normalize(),
     * so the duplicate check still tells все and всё apart.
     */
    public function matches(string $typed, string $expected, ?string $lang = null): bool
    {
        $typed = $this->normalize($typed);
        $expected = $this->normalize($expected);

        if ($lang !== null && strtolower($lang) === 'ru') {
            $typed = str_replace('ё', 'е', $typed);
            $expected = str_replace('ё', 'е', $expected);
        }

        return $typed === $expected;
    }
}
