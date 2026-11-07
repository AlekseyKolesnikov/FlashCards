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

        // Duplicate key => the saved card, or true once a row of this file has claimed the key.
        $existing = [];
        $columns = ['id', 'lang1_id', 'word1_norm', 'lang2_id', 'word2_norm', 'complexity', 'notes1', 'examples1', 'notes2', 'examples2'];
        foreach (Card::toBase()->get($columns) as $card) {
            $existing[$this->vocabulary->duplicateKey((int) $card->lang1_id, $card->word1_norm, (int) $card->lang2_id, $card->word2_norm)] = $card;
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
                $card["notes{$side}"] = $this->text($row["notes{$side}"] ?? '');
                $card["examples{$side}"] = $this->text($row["examples{$side}"] ?? '');

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
                $saved = $existing[$key];
                $changes = $saved === true ? [] : $this->changes($saved, $card, $row);
                $existing[$key] = true;

                if ($changes === []) {
                    $result->duplicates[] = ['row' => $number, 'word1' => $card['word1'], 'word2' => $card['word2']];
                } else {
                    $swapped = (int) $saved->lang1_id !== $card['lang1_id'];
                    $result->updates[] = [
                        'row' => $number,
                        'id' => (int) $saved->id,
                        'word1' => $swapped ? $card['word2'] : $card['word1'],
                        'word2' => $swapped ? $card['word1'] : $card['word2'],
                        'changes' => $changes,
                    ];
                }

                continue;
            }

            $existing[$key] = true;
            $result->valid[] = $card;
        }

        return $result;
    }

    /**
     * Fields of a saved card that the row sets differently, as column => [old, new] in the
     * saved card's language order. Only columns present in the file count; an empty
     * complexity leaves the saved one alone instead of resetting it to the default.
     *
     * @param  array<string, mixed>  $card  The checked row.
     * @param  array<string, string>  $row  The raw row, to see which columns the file has.
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changes(object $saved, array $card, array $row): array
    {
        $changes = [];

        if (($row['complexity'] ?? '') !== '' && (int) $saved->complexity !== $card['complexity']) {
            $changes['complexity'] = [(int) $saved->complexity, $card['complexity']];
        }

        $swapped = (int) $saved->lang1_id !== $card['lang1_id'];
        foreach ([1, 2] as $side) {
            $target = $swapped ? 3 - $side : $side;

            foreach (['notes', 'examples'] as $field) {
                if (! array_key_exists("{$field}{$side}", $row)) {
                    continue;
                }

                $old = $saved->{"{$field}{$target}"};
                $new = $card["{$field}{$side}"];
                // Saved text may have Windows line breaks (from the card form or older imports).
                if ($this->text((string) $old) !== $new) {
                    $changes["{$field}{$target}"] = [$old, $new];
                }
            }
        }

        return $changes;
    }

    /**
     * Notes or examples as saved: line breaks as "\n", empty as null.
     */
    private function text(string $value): ?string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return $value === '' ? null : $value;
    }

    /**
     * Save the valid cards and, if asked, the changes to existing cards, in one transaction.
     *
     * @return array{imported: int, updated: int}
     */
    public function import(ImportResult $result, bool $updateExisting = false): array
    {
        $now = now();

        DB::transaction(function () use ($result, $now, $updateExisting) {
            foreach (array_chunk($result->valid, 500) as $chunk) {
                Card::insert(array_map(fn ($card) => $card + ['created_at' => $now, 'updated_at' => $now], $chunk));
            }

            if ($updateExisting) {
                foreach ($result->updates as $update) {
                    Card::whereKey($update['id'])->update(array_map(fn (array $change) => $change[1], $update['changes']));
                }
            }
        });

        return ['imported' => count($result->valid), 'updated' => $updateExisting ? count($result->updates) : 0];
    }
}
