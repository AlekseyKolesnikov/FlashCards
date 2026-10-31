<?php

namespace App\Services\Import;

use App\Models\Card;
use App\Models\Language;
use App\Services\AnswerChecker;
use App\Services\Vocabulary;
use Illuminate\Support\Facades\DB;

/**
 * Checks imported rows against the card rules (SPECIFICATION.md §4) and saves the valid ones.
 */
class CardImporter
{
    public function __construct(private AnswerChecker $checker, private Vocabulary $vocabulary) {}

    /**
     * @param  array<int, array<string, string>>  $rows  Card fields (language codes) keyed by row number.
     */
    public function check(array $rows): ImportResult
    {
        $result = new ImportResult;
        $languages = Language::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id]);
        $complexity = config('flashcards.complexity');

        $existing = [];
        foreach (Card::toBase()->get(['lang1_id', 'word1_norm', 'lang2_id', 'word2_norm']) as $card) {
            $existing[$this->vocabulary->duplicateKey($card->lang1_id, $card->word1_norm, $card->lang2_id, $card->word2_norm)] = true;
        }

        foreach ($rows as $number => $row) {
            $errors = [];
            $card = [];

            foreach ([1, 2] as $side) {
                $code = mb_strtolower($row["lang{$side}"] ?? '');
                $card["lang{$side}_id"] = $languages[$code] ?? null;

                if ($code === '') {
                    $errors[] = "Language {$side} is missing.";
                } elseif ($card["lang{$side}_id"] === null) {
                    $errors[] = "Unknown language code \"{$code}\".";
                }

                $card["word{$side}"] = $row["word{$side}"] ?? '';
                $card["notes{$side}"] = ($row["notes{$side}"] ?? '') === '' ? null : $row["notes{$side}"];
                $card["examples{$side}"] = ($row["examples{$side}"] ?? '') === '' ? null : $row["examples{$side}"];

                if ($card["word{$side}"] === '') {
                    $errors[] = "Word {$side} is missing.";
                } elseif (mb_strlen($card["word{$side}"]) > 255) {
                    $errors[] = "Word {$side} is longer than 255 characters.";
                }
                if (mb_strlen((string) $card["notes{$side}"]) > 255) {
                    $errors[] = "Notes {$side} are longer than 255 characters.";
                }
                if (mb_strlen((string) $card["examples{$side}"]) > 2000) {
                    $errors[] = "Examples {$side} are longer than 2000 characters.";
                }
            }

            if ($card['lang1_id'] !== null && $card['lang1_id'] === $card['lang2_id']) {
                $errors[] = 'Language 1 and language 2 must differ.';
            }

            $value = $row['complexity'] ?? '';
            if ($value === '') {
                $card['complexity'] = $complexity['default'];
            } elseif (ctype_digit($value) && (int) $value >= $complexity['min'] && (int) $value <= $complexity['max']) {
                $card['complexity'] = (int) $value;
            } else {
                $errors[] = "Complexity must be a whole number from {$complexity['min']} to {$complexity['max']}.";
            }

            if ($errors !== []) {
                $result->errors[] = ['row' => $number, 'message' => implode(' ', $errors)];

                continue;
            }

            $card['word1_norm'] = $this->checker->normalize($card['word1']);
            $card['word2_norm'] = $this->checker->normalize($card['word2']);
            $key = $this->vocabulary->duplicateKey($card['lang1_id'], $card['word1_norm'], $card['lang2_id'], $card['word2_norm']);

            // Also catches a card that appears twice in the same file.
            if (isset($existing[$key])) {
                $result->duplicates[] = ['row' => $number, 'word1' => $card['word1'], 'word2' => $card['word2']];

                continue;
            }

            $existing[$key] = true;
            $result->valid[] = $card;
        }

        return $result;
    }

    /**
     * Save the valid cards in one transaction.
     */
    public function import(ImportResult $result): int
    {
        $now = now();

        DB::transaction(function () use ($result, $now) {
            foreach (array_chunk($result->valid, 500) as $chunk) {
                Card::insert(array_map(fn ($card) => $card + ['created_at' => $now, 'updated_at' => $now], $chunk));
            }
        });

        return count($result->valid);
    }
}
