<?php

namespace App\Services;

use App\Enums\StudySessionStatus;
use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\StudySessionCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vocabulary rules from SPECIFICATION.md §4.3.
 */
class Vocabulary
{
    public function __construct(private AnswerChecker $checker) {}

    /**
     * A key that is equal for duplicate cards, whatever order their languages are stored in.
     */
    public function duplicateKey(int $lang1Id, string $word1Norm, int $lang2Id, string $word2Norm): string
    {
        $side1 = $lang1Id.':'.$word1Norm;
        $side2 = $lang2Id.':'.$word2Norm;

        return $lang1Id < $lang2Id ? "{$side1}|{$side2}" : "{$side2}|{$side1}";
    }

    /**
     * The existing card that the given card data would duplicate, if any.
     *
     * @param  array{lang1_id: int, word1: string, lang2_id: int, word2: string}  $data
     */
    public function findDuplicate(array $data, ?int $exceptId = null): ?Card
    {
        $norm1 = $this->checker->normalize($data['word1']);
        $norm2 = $this->checker->normalize($data['word2']);

        return Card::query()
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->where('lang1_id', $data['lang1_id'])->where('word1_norm', $norm1)
                    ->where('lang2_id', $data['lang2_id'])->where('word2_norm', $norm2))
                ->orWhere(fn (Builder $q) => $q
                    ->where('lang1_id', $data['lang2_id'])->where('word1_norm', $norm2)
                    ->where('lang2_id', $data['lang1_id'])->where('word2_norm', $norm1)))
            ->first();
    }

    /**
     * Other cards of the same language pair that share a word with this card.
     * Learners would see the same prompt with a different expected answer.
     *
     * @return Collection<int, Card>
     */
    public function cardsSharingAWord(Card $card): Collection
    {
        $sharesWord = function (Builder $q, int $langId, string $norm) {
            $q->where(fn (Builder $q) => $q->where('lang1_id', $langId)->where('word1_norm', $norm))
                ->orWhere(fn (Builder $q) => $q->where('lang2_id', $langId)->where('word2_norm', $norm));
        };

        return Card::query()
            ->whereKeyNot($card->id)
            ->forLanguagePair($card->lang1_id, $card->lang2_id)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $sharesWord($q, $card->lang1_id, $card->word1_norm))
                ->orWhere(fn (Builder $q) => $sharesWord($q, $card->lang2_id, $card->word2_norm)))
            ->get();
    }

    /**
     * Update a card. Changing its languages resets learners' progress for it;
     * swapping the two sides keeps it.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Card $card, array $data): void
    {
        DB::transaction(function () use ($card, $data) {
            $before = [$card->lang1_id, $card->lang2_id];
            $card->update($data);
            $after = [$card->lang1_id, $card->lang2_id];

            sort($before);
            sort($after);

            if ($before !== $after) {
                LearnerCard::where('card_id', $card->id)->delete();
                StudySessionCard::where('card_id', $card->id)
                    ->whereHas('studySession', fn (Builder $q) => $q->where('status', StudySessionStatus::Open))
                    ->delete();
                $this->finishEmptySessions();
            }
        });
    }

    /**
     * Delete cards permanently, together with learners' progress for them.
     *
     * @param  array<int, int>  $ids
     */
    public function delete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            // Progress and session entries go with the card (cascading foreign keys).
            $deleted = Card::whereKey($ids)->delete();
            $this->finishEmptySessions();

            return $deleted;
        });
    }

    /**
     * Delete an unused language, with any study sessions left over for it.
     */
    public function deleteLanguage(Language $language): void
    {
        DB::transaction(function () use ($language) {
            StudySession::where('from_lang_id', $language->id)->orWhere('to_lang_id', $language->id)->delete();
            $language->delete();
        });
    }

    /**
     * Language pairs that have cards, each listed once with the lower language ID first.
     *
     * @return list<array{key: string, label: string}>
     */
    public function languagePairs(): array
    {
        $codes = Language::pluck('code', 'id');

        return Card::query()
            ->selectRaw('LEAST(lang1_id, lang2_id) AS a, GREATEST(lang1_id, lang2_id) AS b')
            ->distinct()
            ->toBase()
            ->get()
            ->map(fn ($pair) => [
                'key' => "{$pair->a}-{$pair->b}",
                'label' => $codes[$pair->a].' – '.$codes[$pair->b],
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * Parse a "3-7" language pair key.
     *
     * @return array{int, int}|null
     */
    public function parsePairKey(?string $key): ?array
    {
        if (! $key || ! preg_match('/^(\d+)-(\d+)$/', $key, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * Mark open sessions that have no unanswered cards left as finished.
     */
    private function finishEmptySessions(): void
    {
        StudySession::where('status', StudySessionStatus::Open)
            ->whereDoesntHave('cards', fn (Builder $q) => $q->where('done', false))
            ->update(['status' => StudySessionStatus::Finished, 'finished_at' => now()]);
    }
}
